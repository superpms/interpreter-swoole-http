# 扩展点与注意事项

## 可扩展位置

### 终端命令挂载

入口：`bin/autorun.php`

适合修改：

- 新增本包自己的控制命令
- 调整命令类挂载条件

注意：不要在这里写业务命令逻辑；命令类应放在 `src/pms/program/swooleHttp/` 或清晰的包内模块下。

### Swoole server 启动参数

入口：`SwooleHttpCommand::entry()`

适合修改：

- 默认 settings
- `http.swoole.config` 合并规则
- 事件回调
- `LIFECYCLE_SERVER_BOOTED` 传参

注意：当前 `reload_async`、`enable_coroutine`、`daemonize` 会覆盖配置同名键。调整合并顺序会改变包的外部配置语义。

### 服务状态管理

入口：`SwooleHttpServerControl`

适合修改：

- state file 内容
- PID 匹配规则
- restart 等待条件
- 控制命令生成方式

注意：状态清理必须继续避免误删新服务状态。删除状态文件前应确认 master PID 匹配。

### Request 适配

入口：`SwooleHttpRequest`

适合修改：

- Swoole request 字段到 PMS request 字段的映射
- server/header 字段补齐
- body 读取策略
- Swoole 原生 request 方法暴露

注意：父类 `HttpRequest::init()` 承担标准化逻辑。不要在本类里重复实现 host、scheme、params、JSON body 合并，除非要改变 Swoole 专属行为。

### Response 适配

入口：`SwooleHttpResponse`

适合修改：

- Swoole response 方法代理
- cookie/header/status/sendfile 行为
- websocket / HTTP2 相关方法的显式声明

注意：保持 `HttpResponseInject` 兼容。修改通用响应语义时要同时评估普通 `HttpResponse`。

### 请求外层兜底

入口：`SwooleHttpCommand::handleRequestThrowable()`

适合修改：

- sandbox 外层异常 JSON
- response 不可写时的记录方式
- `error_debug` 下的输出字段

注意：业务接口异常通常已经由 `interpreter-http` 的 `Sandbox::exceptionHandle()` 接管，不应在外层兜底里硬编码业务异常格式。

## 不建议在本包扩展的内容

| 内容 | 应去哪里 |
| --- | --- |
| 业务路由规则 | `superpms/interpreter-http` 或应用路由结构 |
| 业务接口参数 | 对应业务端应用 |
| 业务响应字段 | 业务 `HttpApp` 或 exception handle |
| 通用中间件执行顺序 | `interpreter-http` 的 `Sandbox` |
| 普通 PHP-FPM 响应行为 | `interpreter-http` 的 `HttpResponse` |

## 常驻进程开发注意事项

Swoole HTTP 是常驻进程，开发时要主动区分“服务级状态”和“请求级状态”。

请求级状态包括：

- 当前 request / response
- 当前错误上下文
- 当前 VarDumper response
- 当前路由注入
- 当前业务对象

这些状态不应长期留在全局静态上下文中。本包已经在 request 回调前后清理了部分框架上下文，扩展代码也应遵守这个边界。

## 协程注意事项

当前 settings 固定开启：

```php
'enable_coroutine' => true
```

并使用 `SWOOLE_PROCESS` 模式。涉及数据库、Redis 或其他连接资源时，应确认依赖包是否已经按 `in_swoole()` 和 HTTP 生命周期钩子切换到适合常驻进程的连接管理方式。

## 修改前检查清单

1. 这个问题是否真的属于 Swoole 运行时，而不是 HTTP sandbox 主链？
2. 是否会影响 restart/status 对已有进程的识别？
3. 是否会让 request 级对象跨请求泄漏？
4. 是否会改变 `http.swoole.config` 的覆盖语义？
5. 是否需要同步普通 HTTP response/request 的行为？
6. 是否需要更新 README 或对应 docs 分区？

## 测试建议

本包没有独立测试入口时，至少做这些手动验证：

1. `php pms swoole-http-server --host 127.0.0.1 --port <port>`
2. 访问一个静态文件路径，验证 WebRoot
3. 访问一个已存在 HTTP app 路由，验证 sandbox 复用
4. `php pms swoole-http-control status`
5. `php pms swoole-http-control reload`
6. `php pms swoole-http-control restart`
7. `php pms swoole-http-control stop`
8. 检查 runtime 下 PID、state、log 是否符合预期
