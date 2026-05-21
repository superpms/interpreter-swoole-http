# 日志与调试

## Swoole 服务日志

`SwooleHttpCommand` 默认给 Swoole settings 设置：

```php
'log_file' => Path::getRuntime('/interpreter/log/swoole-http.log')
```

如果 `http.swoole.config` 中也传入 `log_file`，当前实现允许该配置覆盖内置默认值，因为 `$setConfig` 在内置 `log_file` 之后展开。

## restart 日志

`SwooleHttpServerControl::restart()` 会生成：

```text
runtime/interpreter/log/swoole-http.restart.<YmdHis>.log
```

该日志用于查看 restart 后台拉起命令的输出。

## 状态文件

状态文件：

```text
runtime/interpreter/pid/swoole-http.json
```

可用于排查：

- host / port / web_root 是否符合预期
- master_pid / manager_pid 是否正确
- start_command / start_argv 是否能用于 restart
- settings 是否为当前启动参数
- reload_count 和 reload 时间是否更新

## request 外层异常

`SwooleHttpCommand::handleRequestThrowable()` 处理 request 回调内、HTTP sandbox 外层捕获到的 `Throwable`。

当 Swoole response 不可写时，方法直接返回。

当 response 可写时：

- 状态码设为 `500 Server Error`
- `content-type` 设为 `JSON_CONTENT_TYPE`
- `BootOptions::get_error_debug()` 为 true 时输出 message、error、file、line、trace
- `error_debug` 为 false 时只输出通用 500 JSON

这层是 Swoole request 回调的兜底，不替代 `interpreter-http` 的 `Sandbox::exceptionHandle()`。

## 请求内异常

已经进入 `pms\interpreter\http\Sandbox` 的异常由 `interpreter-http` 处理：

- `Sandbox::exceptionHandle()`
- 路由对应的 exception handle 类
- 默认 `pms\HttpExceptionHandle`

如果业务接口抛出的异常格式不对，应优先检查 `interpreter-http` 和业务路由的 exception handle，而不是先改 Swoole 包。

## VarDumper 与 dd

本包在启动时调用 `initVarDumper()`，把 Symfony VarDumper 输出绑定到当前 Swoole response。

行为：

- 每个请求开始时把当前 `Swoole\Http\Response` 放入 `Ctx`
- dump 时如果 response 可写，设置 HTTP 500
- 使用 `HtmlDumper` 输出 HTML
- 请求结束时清理 response 上下文

这使 `dd()` / dump 类调试在 Swoole HTTP response 中有输出目标，而不是依赖 PHP-FPM 的 header / echo 模型。

## 错误上下文清理

request 回调开始和结束都会调用 `pms_error_clear()`。

在常驻进程中，错误上下文是跨代码路径共享的槽位；如果不清理，上一请求的异常可能影响下一请求的兜底判断。

## 调试排查顺序

1. 服务是否启动：`php pms swoole-http-control status`
2. 状态文件是否存在且 PID 匹配
3. Swoole log 是否有启动或运行错误
4. restart 时查看 restart 专用日志
5. 请求是否进入 `SwooleHttpCommand` 的 request 回调
6. 请求是否成功构造 `SwooleHttpRequest` / `SwooleHttpResponse`
7. 异常发生在 sandbox 外层还是 `interpreter-http` sandbox 内部
8. `error_debug` 是否决定了响应中是否包含详细错误字段
