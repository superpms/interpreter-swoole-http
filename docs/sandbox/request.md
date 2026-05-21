# SwooleHttpRequest

`pms\interpreter\swooleHttp\sandbox\SwooleHttpRequest` 是 Swoole 原生 request 到 PMS HTTP request 注入协议的适配层。

它继承：

```php
pms\interpreter\http\sandbox\HttpRequest
```

父类负责通用 HTTP request 能力，例如：

- `server()` / `header()` / `get()` / `post()` / `files()` / `cookie()`
- `input()` / `getContent()` / `rawContent()`
- `method()` / `isPost()` / `isGet()` / `isOptions()`
- `pathinfo()` / `domain()` / `builder()`
- `init()` 中的 HTTPS、IP、host、content type、JSON body 合并、params 合并

本类只覆盖与 Swoole 平台相关的数据来源。

## 构造阶段

父类 `HttpRequest::__construct(...$args)` 是 final，会调用子类的 `platformConstruct(...$args)`。

`SwooleHttpRequest::platformConstruct()` 接收：

```php
Swoole\Http\Request $request
```

并写入：

```php
$this->request = $request;
$this->server = $request->server ?? [];
```

随后父类根据 `$this->server` 解析：

- request method
- request uri
- pathinfo

## 初始化阶段

HTTP sandbox 在确认路由进入应用后调用 `$this->request->init()`，父类 `init()` 会调用子类 `platformInit()`。

`SwooleHttpRequest::platformInit()` 从 Swoole request 中填充：

| 父类字段 | Swoole 来源 |
| --- | --- |
| `$header` | `$request->header ?? []` |
| `$cookie` | `$request->cookie ?? []` |
| `$get` | `$request->get ?? []` |
| `$post` | `$request->post ?? []` |
| `$files` | `$request->files ?? []` |
| `$input` | `$request->getContent()` |

同时会对 `$server` 执行 `ksort()`，并把 `$inputLoaded` 置为 true。

## Swoole 原生方法透出

本类保留了 Swoole request 的部分原生能力：

- `getContent()`
- `rawContent()`
- `getData()`
- `getMethod()`
- `parse(string $data)`
- `isCompleted()`

这些方法直接委托给 `Swoole\Http\Request`。

## 与父类 init 的关系

`platformInit()` 只装载原始字段。真正的标准化仍在父类 `HttpRequest::init()` 中完成：

- 计算 `isHttps`
- 计算客户端 IP
- 计算 host / scheme / content type
- JSON body 合并到 post
- 合并 get / post / files 为 params

因此修改“如何从 Swoole request 取值”时看本类；修改“取值后如何标准化”时看 `interpreter-http` 的 `HttpRequest`。

## 注意事项

- Swoole header key 通常是小写；父类 `header()` 会同时尝试原名、小写和大写。
- `$request->server['request_uri']` 会被父类用于 pathinfo 解析。
- 当前本类只读取 `$request->server` 原始数据并交给父类通用初始化；如果 Swoole server 数据缺少某些普通 HTTP server 字段，应在本类中补齐，而不是改业务代码读取特殊字段。
- body 在 `platformInit()` 中会立即读取并缓存到 `$input`，后续 `input()` 读取的是同一份内容。
