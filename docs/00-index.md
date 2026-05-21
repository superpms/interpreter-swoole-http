# interpreter-swoole-http 开发文档

本文档面向 `superpms/interpreter-swoole-http` 的包开发者，说明这个 Composer 包自己的功能、接入方式、运行时机制、模块边界和扩展点。

它不是业务端接口文档。业务接口、路由资源和业务响应字段应回到对应应用或 `superpms/interpreter-http` 的业务执行链中查看。

## 先读

1. [安装与自动挂载](getting-started/installation.md)
2. [Boot 挂载与启动命令](getting-started/boot-and-commands.md)
3. [SwooleHttpCommand 运行时](runtime/server-command.md)
4. [HTTP sandbox 复用边界](http-compatibility/http-sandbox-reuse.md)

## 按问题读

- 想知道包如何装入框架：读 [安装与自动挂载](getting-started/installation.md)
- 想知道怎么启动、后台运行和控制服务：读 [Boot 挂载与启动命令](getting-started/boot-and-commands.md) 与 [进程控制](operations/process-control.md)
- 想改监听地址、端口、Swoole settings 或 WebRoot：读 [SwooleHttpCommand 运行时](runtime/server-command.md) 与 [配置项参考](reference/configuration.md)
- 想理解 PID、状态文件、restart 逻辑：读 [server control](runtime/server-control.md)
- 想理解每次请求如何进入 PMS HTTP 主链：读 [生命周期](runtime/lifecycle.md) 与 [HTTP sandbox 复用边界](http-compatibility/http-sandbox-reuse.md)
- 想改 Swoole request / response 的适配：读 [SwooleHttpRequest](sandbox/request.md) 与 [SwooleHttpResponse](sandbox/response.md)
- 想排查日志、`dd()`、异常 JSON 或调试字段：读 [日志与调试](operations/logging-and-debug.md)
- 想查类清单和扩展位置：读 [公共类索引](reference/public-classes.md) 与 [扩展点](reference/extension-points.md)

## 模块结构

```text
docs/
├── getting-started/
│   ├── installation.md
│   └── boot-and-commands.md
├── runtime/
│   ├── server-command.md
│   ├── server-control.md
│   └── lifecycle.md
├── http-compatibility/
│   └── http-sandbox-reuse.md
├── sandbox/
│   ├── request.md
│   └── response.md
├── operations/
│   ├── process-control.md
│   └── logging-and-debug.md
└── reference/
    ├── configuration.md
    ├── public-classes.md
    └── extension-points.md
```

## 不在这里读

- 业务接口参数、业务路由和业务返回结构不属于本包文档。
- 普通 PHP-FPM / Web Server 模式的完整解释器文档不在这里展开，只在必要处说明与 `superpms/interpreter-http` 的复用关系。
- 数据库、Redis、业务中间件的内部实现不在这里展开；本文档只说明 Swoole 常驻模式下它们通过 HTTP 生命周期钩子接入时需要注意的边界。
