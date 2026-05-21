# SwooleHttpResponse

`pms\interpreter\swooleHttp\sandbox\SwooleHttpResponse` 是 PMS HTTP response 注入协议到 Swoole 原生 response 的适配层。

它继承：

```php
pms\interpreter\http\sandbox\HttpResponse
```

但当前实现将主要响应动作委托给：

```php
Swoole\Http\Response
```

## 构造

```php
public function __construct(Response $response)
{
    $this->response = $response;
    parent::__construct();
}
```

父类构造目前为空；保留调用可以维持与 `HttpResponse` 基类未来初始化逻辑的兼容。

## 原生委托

本类通过两种方式暴露 Swoole response：

```php
public function __call(string $name, array $arguments)
public function __get(string $name)
```

未显式声明的方法会转给 `$this->response`，属性读取也转给 Swoole response。

同时，常用响应方法显式声明并委托：

- `isWritable()`
- `initHeader()`
- `cookie()` / `setCookie()`
- `rawcookie()` / `setRawCookie()`
- `status()` / `setStatusCode()`
- `header()` / `setHeader()`
- `trailer()`
- `ping()` / `goaway()`
- `write()`
- `end()`
- `sendfile()`
- `redirect()`
- `detach()`
- `upgrade()` / `push()` / `recv()` / `close()`

显式声明的价值是让 PMS HTTP sandbox 依赖的 `HttpResponseInject` 方法在 Swoole 环境中有确定行为，同时保留 Swoole 扩展能力。

## create

`create(object|array|int $server = -1, int $fd = -1)` 调用 `Response::create($server, $fd)`。

如果 Swoole 返回 false，本方法返回 false；否则包装成新的 `SwooleHttpResponse`。

## 与普通 HttpResponse 的差异

普通 `HttpResponse` 使用 PHP 原生 `header()`、`echo`、`flush()`、`readfile()` 和 `exit()` 实现响应输出。

`SwooleHttpResponse` 使用 Swoole response：

- `header()` 写入 Swoole 响应头
- `status()` 写入 Swoole 状态码
- `write()` 写入 Swoole chunk
- `end()` 结束 Swoole 响应
- `sendfile()` 交给 Swoole 发送文件
- `isWritable()` 按 Swoole response 状态判断

因此 Swoole HTTP 请求不应依赖 PHP-FPM 的 header 发送状态或 `exit()` 结束方式。

## 在 HTTP sandbox 中的使用

`interpreter-http` 的 `Sandbox` 只关心 response 是否满足 `HttpResponseInject` 协议。它会调用：

- `header()`
- `status()` / `setStatusCode()`
- `end()`
- `sendfile()`
- `isWritable()`

这些调用在 Swoole 模式下都落到 Swoole response。

## 注意事项

- `end()` 后 response 通常不可再写，外层兜底也会先检查 `isWritable()`。
- `write()` 后仍需要由业务或 sandbox 调用 `end()` 结束响应。
- 因为 `__call()` 会把未知方法转给原生 response，新增使用 Swoole response 特性时要确认该方法在目标 Swoole 版本存在。
- 修改通用 HTTP response 协议时应同步评估普通 `HttpResponse` 和本类，否则两个运行时可能出现行为差异。
