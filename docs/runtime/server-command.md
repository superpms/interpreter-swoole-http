# SwooleHttpCommand 运行时

`pms\program\swooleHttp\SwooleHttpCommand` 是 `swoole-http-server` 命令的实现类，负责把 PMS HTTP sandbox 包进 Swoole HTTP Server。

## 命令定义

```php
protected string $name = "swoole-http-server";
protected string $description = "启动 swoole-http 服务";
```

命令参数定义在 `$validate`：

- `host`：绑定地址
- `port`：绑定端口
- `root`：Web 根目录
- `daemonize`：是否守护进程运行

## 启动流程

`entry()` 的主流程为：

1. 触发 `HttpLifecycleHook::run(LIFECYCLE_BOOT)`
2. 解析 WebRoot，并挂载 `Path::mount('WebRoot', $webRoot)`
3. 解析 host、port 和 `http.swoole.config`
4. 触发 `HttpLifecycleHook::run(LIFECYCLE_BOOTED)`
5. 初始化 VarDumper handler
6. 创建 `new Swoole\Http\Server($host, $port, SWOOLE_PROCESS)`
7. 合并 Swoole settings
8. 注册 `start`、`beforeReload`、`afterReload`、`shutdown`、`request` 事件
9. 触发 `HttpLifecycleHook::run(LIFECYCLE_SERVER_BOOTED, $http)`
10. 调用 `$http->start()`

## Swoole settings

命令内置 settings：

```php
[
    'log_file' => Path::getRuntime('/interpreter/log/swoole-http.log'),
    'pid_file' => SwooleHttpServerControl::pidFile(),
    'max_wait_time' => 10,
    ...$setConfig,
    'reload_async' => true,
    'enable_coroutine' => true,
    'daemonize' => $daemonize,
]
```

其中 `$setConfig` 来自 `config('http.swoole.config', [])`。注意合并顺序：`reload_async`、`enable_coroutine` 和 `daemonize` 写在展开配置之后，因此当前实现会强制覆盖同名配置。

## start 事件

`start` 回调会写入状态文件，内容包括：

- host / port / web_root / root_path
- PHP binary 和命令 binary
- start command 与 start argv
- master_pid / manager_pid
- Swoole settings
- started_at / started_at_text
- reload_count

写入由 `SwooleHttpServerControl::writeState(..., true)` 完成，状态文件路径见 [server control](server-control.md)。

## reload 事件

`beforeReload` 会更新：

- `before_reload_at`
- `before_reload_at_text`
- `reload_count + 1`

`afterReload` 会更新：

- `manager_pid`
- `after_reload_at`
- `after_reload_at_text`

## shutdown 事件

`shutdown` 回调调用 `SwooleHttpServerControl::removeStateIfMaster($server->master_pid)`。

只有状态文件中的 `master_pid` 与当前 master PID 一致时，状态文件和对应 PID 文件才会被删除。这避免误删其他新启动服务的状态。

## request 事件

每个 Swoole 请求进入时：

1. `pms_error_clear()`
2. `Ctx::set(HttpRequestInject::class, null)`
3. 保存当前 Swoole response 到 VarDumper 上下文
4. 构造 `SwooleHttpRequest`
5. 构造 `SwooleHttpResponse`
6. 创建 `pms\interpreter\http\Sandbox`
7. 执行 `$exp->run()`

捕获到 `Throwable` 时，`handleRequestThrowable()` 会在 response 仍可写时输出 500 JSON。`BootOptions::get_error_debug()` 为真时包含 message、error、file、line、trace；否则只输出通用错误。

finally 阶段会清理：

- `HttpRequestInject`
- VarDumper response 上下文
- PMS 错误上下文

常驻进程中这一步很重要，避免一次请求的注入对象或错误状态污染后续请求。

## VarDumper 适配

`initVarDumper()` 只初始化一次。它把 Symfony VarDumper 输出绑定到当前请求的 Swoole response：

- response 不可写时直接返回
- 先设置 HTTP 500
- 输出 `text/html`
- 使用 `$response->write($output)` 写入 dump HTML

当前请求的 response 通过 `Ctx` 中的 `swoole_http_response` 读取，请求结束时会被清空。
