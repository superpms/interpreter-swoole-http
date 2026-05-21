# superpms/interpreter-swoole-http

`superpms/interpreter-swoole-http` 为 PMS HTTP 解释器提供 Swoole HTTP Server 运行时。它不重新实现路由、接口执行或业务响应协议，而是在 Swoole 常驻进程中把 `Swoole\Http\Request` / `Swoole\Http\Response` 适配成 `superpms/interpreter-http` 已有的 request / response sandbox，再交给 HTTP sandbox 主链执行。

## 包定位

- Composer 包名：`superpms/interpreter-swoole-http`
- PHP 版本：`>=8.1`
- 依赖：`superpms/basic`、`superpms/interpreter-terminal`、`superpms/interpreter-http`
- 注册命令：`swoole-http-server`、`swoole-http-control`
- 运行模型：CLI 启动的 Swoole 常驻 HTTP 服务

## 安装

```bash
composer require superpms/interpreter-swoole-http
```

该包通过 Composer `autoload.files` 加载 `bin/autoload.php`，再由 `bin/autorun.php` 在 `TerminalCommandHook` 存在时挂载终端命令。

## 最小启动示例

```bash
php pms swoole-http-server --host 127.0.0.1 --port 9999 --root public
```

常用控制命令：

```bash
php pms swoole-http-control status
php pms swoole-http-control reload
php pms swoole-http-control stop
php pms swoole-http-control restart
```

后台运行：

```bash
php pms swoole-http-server --daemonize 1
```

## 配置入口

启动命令会读取这些配置，并允许部分值被命令行参数覆盖：

- `http.web_root`：默认 Web 根目录，默认值 `/public`
- `http.swoole.host`：默认监听地址，默认值 `127.0.0.1`
- `http.swoole.port`：默认监听端口，默认值 `9999`
- `http.swoole.config`：传给 `Swoole\Http\Server::set()` 的配置数组

运行时状态默认写入框架 runtime：

- PID 文件：`runtime/interpreter/pid/swoole-http.pid`
- 状态文件：`runtime/interpreter/pid/swoole-http.json`
- 服务日志：`runtime/interpreter/log/swoole-http.log`
- restart 拉起日志：`runtime/interpreter/log/swoole-http.restart.<YmdHis>.log`

## 工作方式

启动阶段：

1. 触发 `HttpLifecycleHook::run(LIFECYCLE_BOOT)`
2. 解析 WebRoot、host、port、Swoole settings
3. 初始化 VarDumper 到当前 Swoole response 的输出适配
4. 创建 `Swoole\Http\Server`
5. 注册 `start`、`beforeReload`、`afterReload`、`shutdown`、`request` 回调
6. 触发 `HttpLifecycleHook::run(LIFECYCLE_SERVER_BOOTED, $http)`
7. 调用 `$http->start()`

请求阶段：

1. 清理当前请求错误上下文和 request 注入
2. 构造 `SwooleHttpRequest`
3. 构造 `SwooleHttpResponse`
4. 创建 `pms\interpreter\http\Sandbox`
5. 复用 `interpreter-http` 的路由、静态文件、中间件、接口执行、异常处理与响应序列化机制
6. finally 阶段清理 request 注入、VarDumper response 上下文和错误上下文

## 文档导航

- [docs/00-index.md](docs/00-index.md)：文档总入口
- [docs/getting-started/installation.md](docs/getting-started/installation.md)：安装、自动挂载与启动前提
- [docs/getting-started/boot-and-commands.md](docs/getting-started/boot-and-commands.md)：Boot 挂载、启动命令和最小用法
- [docs/runtime/server-command.md](docs/runtime/server-command.md)：`SwooleHttpCommand` 运行时细节
- [docs/runtime/server-control.md](docs/runtime/server-control.md)：`SwooleHttpServerControl` 与状态文件
- [docs/runtime/lifecycle.md](docs/runtime/lifecycle.md)：常驻进程生命周期与请求清理
- [docs/http-compatibility/http-sandbox-reuse.md](docs/http-compatibility/http-sandbox-reuse.md)：与 `interpreter-http` 的复用边界
- [docs/sandbox/request.md](docs/sandbox/request.md)：`SwooleHttpRequest` 映射
- [docs/sandbox/response.md](docs/sandbox/response.md)：`SwooleHttpResponse` 映射
- [docs/operations/process-control.md](docs/operations/process-control.md)：start / stop / reload / restart / status 运维语义
- [docs/operations/logging-and-debug.md](docs/operations/logging-and-debug.md)：日志、调试输出与错误响应
- [docs/reference/configuration.md](docs/reference/configuration.md)：配置项参考
- [docs/reference/public-classes.md](docs/reference/public-classes.md)：公共类索引
- [docs/reference/extension-points.md](docs/reference/extension-points.md)：扩展点与注意事项

## 开发边界

本包负责 Swoole HTTP Server 启动、进程控制、请求/响应适配，以及常驻进程下的上下文清理。路由解析、接口类执行、中间件、HTTP 异常处理、静态文件输出和响应内容序列化继续归属于 `superpms/interpreter-http`。

如需修改业务接口、路由规则或业务响应结构，不应在本包内处理；本包文档也不包含业务端接口说明。

## License

Apache-2.0。详见 [LICENSE.txt](LICENSE.txt)。
