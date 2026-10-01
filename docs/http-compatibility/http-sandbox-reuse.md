# HTTP Sandbox 复用边界

`superpms/interpreter-swoole-http` 依赖 `superpms/interpreter-http`，但两者不是同一个层级。

本包负责 Swoole 运行时外壳；`interpreter-http` 负责 HTTP 请求进入 PMS 应用后的主执行链。

## 当前复用方式

在 `SwooleHttpCommand` 的 `request` 回调中：

```php
$myRequest = new SwooleHttpRequest($request);
$myResponse = new SwooleHttpResponse($response);
\pms\interpreter\http\Interpreter::dispatch($myRequest, $myResponse);
```

HTTP 业务处理使用的 `Sandbox` 是：

```php
pms\interpreter\http\Sandbox
```

也就是说，Swoole 请求最终仍进入普通 HTTP sandbox。

## 本包替换了什么

本包替换的是 request / response 的平台层：

| 普通 HTTP | Swoole HTTP |
| --- | --- |
| `pms\interpreter\http\sandbox\HttpRequest` | `pms\interpreter\swooleHttp\sandbox\SwooleHttpRequest` |
| `pms\interpreter\http\sandbox\HttpResponse` | `pms\interpreter\swooleHttp\sandbox\SwooleHttpResponse` |

`SwooleHttpRequest` 继承 `HttpRequest`，覆盖平台构造和初始化，把 Swoole 原生 request 数据填入父类约定的字段。

`SwooleHttpResponse` 继承 `HttpResponse`，但把响应写入委托给 Swoole 原生 response。

## 本包没有替换什么

以下能力仍来自 `interpreter-http`：

- CORS header 初始化
- OPTIONS 请求提前结束
- `HttpRoute` 路由解析
- `http.app.route.prefix` 业务路径前缀校验与剥离，以及 `forward()` 指定路径的公共前缀处理
- `request->builder()` 默认添加配置的业务前缀；静态资源地址通过第二参数 `false` 直接拼接
- 静态文件判断和输出
- app / terminal 命中判断
- request `init()` 后的参数合并和 JSON body 解析
- `HttpRequestInject`、`HttpResponseInject`、`HttpRouteInject` 注入
- 应用全局中间件和接口级中间件
- `HttpApp` 子类校验
- `__prepare()` / `entry()` / `__teardown()` 执行
- `contentType` 与 JSON / JSONP / XML / string 响应序列化
- `HttpExceptionHandle` 异常处理
- `LIFECYCLE_SANDBOX_*` 请求级生命周期

因此修改业务路由、接口执行、中间件顺序或返回内容格式时，通常不应该改本包。

## 与普通 HTTP Interpreter 的差异

普通 HTTP 入口是 `pms\interpreter\http\Interpreter::entry()`。它会：

- 创建普通 `HttpRequest` / `HttpResponse`
- 注册 `SystemErrorBroadcast` listener
- 注册 shutdown handler
- 挂载 WebRoot
- 触发 HTTP 生命周期
- 调用 `Interpreter::dispatch()`，按挂载点进入 HTTP 业务 Sandbox 与已注册协议解释器

Swoole HTTP 没有调用 `Interpreter::entry()`。它在 Swoole server 的 request 回调中手动构造 sandbox。

因此本包需要自己处理：

- Swoole server 启动和设置
- WebRoot 挂载
- 请求前后上下文清理
- Swoole response 可写性判断
- 外层 `Throwable` 兜底
- VarDumper 到 Swoole response 的输出

## 开发判断

可以按以下规则判断修改位置：

| 问题 | 优先查看 |
| --- | --- |
| 命令不存在 | 本包 `bin/autorun.php`、terminal hook |
| Swoole 无法启动 | 本包 `SwooleHttpCommand` |
| PID、status、reload、restart 不对 | 本包 `SwooleHttpServerControl` |
| 请求对象字段不对 | 本包 `SwooleHttpRequest`，再看父类 `HttpRequest` |
| header / cookie / end 行为不对 | 本包 `SwooleHttpResponse`，再看父类 `HttpResponse` |
| 路由不命中 | `interpreter-http` 的 `HttpRoute` / `Sandbox` |
| 中间件不执行 | `interpreter-http` 的 `Sandbox::middleware()` |
| 业务返回格式不对 | `interpreter-http` 的 `Sandbox::contentToString()` 或业务 `HttpApp` |
| 请求内异常格式不对 | `interpreter-http` 的 `Sandbox::exceptionHandle()` / `HttpExceptionHandle` |

本包文档只覆盖 Swoole 包自身边界，不复制普通 HTTP 包的完整说明。

## MCP 根入口与转发请求

普通 HTTP 与 Swoole HTTP 共用 Interpreter::dispatch()。HTTP 内置挂载点读取 http.app.route.prefix；安装 interpreter-mcp-http 后，MCP 挂载点读取 mcp.route.prefix，公开 OAuth 元数据入口跟随配置生成。HttpEntrypointHook 在配置加载后匹配处理器提供的路径，按最长完整路径段分派；匹配后由各处理器执行所属链路。MCP 注入可信路径参数后执行宿主认证器。内部 forward() 保持直接进入业务 Sandbox。

SwooleHttpRequest 的 getContent()/rawContent() 返回已初始化请求体，withParams() 副本返回替换后的 JSON 参数；getMethod() 与副本的方法一致。外层请求体、方法和认证上下文保持各自的请求状态。一次性本机 Swoole HTTP 收发验证覆盖初始化、现代发现、资源元数据、OAuth 挑战及实际业务转发。
