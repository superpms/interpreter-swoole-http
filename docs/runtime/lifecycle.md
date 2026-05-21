# 常驻进程生命周期

Swoole HTTP 服务是常驻进程。与普通 HTTP 解释器相比，本包最重要的差异不是路由规则，而是启动一次、处理多次请求时的状态隔离和资源回收边界。

## 服务级生命周期

`SwooleHttpCommand::entry()` 触发的服务级 HTTP 生命周期：

1. `LIFECYCLE_BOOT`
2. `LIFECYCLE_BOOTED`
3. `LIFECYCLE_SERVER_BOOTED`

其中 `LIFECYCLE_SERVER_BOOTED` 会把 `Swoole\Http\Server $http` 作为参数传给钩子。

数据库、Redis 等包可以在 `in_swoole()` 成立时挂载这些钩子。例如当前代码中：

- database 包在 `LIFECYCLE_BOOT` 切换 MySQL connector 到 pool 版本
- redis 包在 `LIFECYCLE_BOOT` 开启 Redis pool 模式

## 请求级生命周期

请求进入后，本包构造 Swoole request / response 适配对象，再调用 `interpreter-http` 的 `Sandbox::run()`。请求级生命周期主要由 HTTP sandbox 触发：

1. `LIFECYCLE_SANDBOX_CREATED`
2. `LIFECYCLE_SANDBOX_BOOT`
3. `LIFECYCLE_SANDBOX_BOOTED`
4. `LIFECYCLE_SANDBOX_RAN`
5. `LIFECYCLE_SANDBOX_DESTRUCT`

`LIFECYCLE_SANDBOX_DESTRUCT` 在 `pms\interpreter\http\Sandbox::__destruct()` 中触发。Swoole HTTP 请求结束后，只要 sandbox 对象正常析构，已挂载的数据库和 Redis 自动回收钩子就有机会执行。

## 请求前清理

request 回调开始时，本包执行：

```php
pms_error_clear();
Ctx::set(HttpRequestInject::class, null);
Ctx::set(static::DUMPER_RESPONSE_KEY, $response);
```

这会清理上一轮请求可能留下的 PMS 错误和 request 注入，同时把当前 Swoole response 放入 VarDumper 输出上下文。

## 请求后清理

request 回调 finally 中执行：

```php
Ctx::set(HttpRequestInject::class, null);
Ctx::set(static::DUMPER_RESPONSE_KEY, null);
pms_error_clear();
```

这是常驻进程下的隔离边界。开发时不要把请求相关对象长时间保存在全局状态、静态属性或跨请求单例中；如果必须缓存，需要明确它不是当前请求私有数据。

## 连接池注意事项

本包本身不创建数据库池或 Redis 池，但它提供 Swoole HTTP 生命周期，使其他包可以在 Swoole 模式下切换到池化实现。

当前相关事实：

- `program-database` 在 `in_swoole()` 且 `HttpLifecycleHook` 存在时，把 MySQL connector 切为 `MysqlPool`
- `program-database` 在 `LIFECYCLE_SANDBOX_DESTRUCT` 调用 `pdb_pool_autoclose()`
- `program-redis` 在 Swoole 下调用 `RDb::isPool(true)`
- `program-redis` 在 `LIFECYCLE_SANDBOX_DESTRUCT` 调用 `prdb_pool_autoclose()`

因此连接借出和归还的正确性依赖 HTTP sandbox 的生命周期完整执行。排查连接泄漏时，应先确认请求是否进入并离开 `Sandbox::run()`，以及 `LIFECYCLE_SANDBOX_DESTRUCT` 是否触发。

## 错误上下文注意事项

普通 HTTP 解释器在入口处注册 `SystemErrorBroadcast` listener 和 shutdown handler。当前 Swoole HTTP 运行时没有复用普通 HTTP `Interpreter::entry()`，而是在每个 Swoole request 回调中直接创建 `Sandbox`。

因此当前 Swoole HTTP 的主要错误出口是：

- `Sandbox::exceptionHandle()`：请求执行期异常
- `SwooleHttpCommand::handleRequestThrowable()`：request 回调外层兜底捕获
- `pms_error_clear()`：请求前后清理错误槽位

如果要调整 Swoole HTTP 的基础错误接管协议，应先确认当前请求是否已经进入 HTTP sandbox，再决定改本包外层兜底还是 `interpreter-http` 的 sandbox 异常处理。
