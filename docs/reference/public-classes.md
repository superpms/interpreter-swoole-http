# 公共类索引

## bin/autoload.php

Composer `autoload.files` 入口。

加载：

- `bin/autorun.php`
- `bin/const.php`

## bin/autorun.php

自动挂载终端命令。

当 `pms\hook\TerminalCommandHook` 存在时，挂载：

- `pms\program\swooleHttp\SwooleHttpCommand`
- `pms\program\swooleHttp\SwooleHttpControlCommand`

## bin/const.php

当前为空。保留包级常量入口。

## pms\program\swooleHttp\SwooleHttpCommand

命令名：`swoole-http-server`

职责：

- 解析启动选项
- 读取 `http.swoole.*` 配置
- 挂载 WebRoot
- 创建并设置 `Swoole\Http\Server`
- 写入服务状态
- 注册 reload / shutdown / request 回调
- 构造 Swoole request / response sandbox 适配
- 调用 `pms\interpreter\http\Sandbox`
- 处理 request 外层 `Throwable`
- 初始化 VarDumper 到 Swoole response 的输出适配

## pms\program\swooleHttp\SwooleHttpControlCommand

命令名：`swoole-http-control`

职责：

- 接收 `status|reload|stop|restart`
- 支持 `--delay`
- 调用 `SwooleHttpServerControl`
- 输出 JSON
- 失败时 `exit(1)`

## pms\program\swooleHttp\SwooleHttpServerControl

职责：

- 定义服务命令名和控制命令名
- 计算 state file 和 PID file
- 读写运行状态
- 清理匹配 master PID 的状态文件
- 生成启动命令和控制命令
- 判断服务状态
- 发送 reload / stop 信号
- 执行 restart 等待与后台拉起
- 识别 PID 命令和端口占用

主要方法：

- `stateFile()`
- `pidFile()`
- `writeState()`
- `readState()`
- `removeStateIfMaster()`
- `startCommand()`
- `controlCommand()`
- `status()`
- `reload()`
- `stop()`
- `restart()`

## pms\interpreter\swooleHttp\sandbox\SwooleHttpRequest

继承：`pms\interpreter\http\sandbox\HttpRequest`

职责：

- 持有 `Swoole\Http\Request`
- 从 Swoole request 填充 server / header / cookie / get / post / files / input
- 透出 Swoole request 的原生内容读取与解析方法

## pms\interpreter\swooleHttp\sandbox\SwooleHttpResponse

继承：`pms\interpreter\http\sandbox\HttpResponse`

职责：

- 持有 `Swoole\Http\Response`
- 把 response 操作委托给 Swoole 原生 response
- 保持 `HttpResponseInject` 兼容
- 通过 `__call()` 和 `__get()` 暴露其他 Swoole response 能力

## 依赖类

本包文档中频繁出现但不归本包所有的类：

| 类 | 所属包 | 本包中的用途 |
| --- | --- | --- |
| `pms\interpreter\http\Sandbox` | `superpms/interpreter-http` | 执行 HTTP 主链 |
| `pms\interpreter\http\sandbox\HttpRequest` | `superpms/interpreter-http` | request 基类 |
| `pms\interpreter\http\sandbox\HttpResponse` | `superpms/interpreter-http` | response 基类 |
| `pms\hook\HttpLifecycleHook` | `superpms/interpreter-http` | HTTP 生命周期钩子 |
| `pms\hook\TerminalCommandHook` | `superpms/interpreter-terminal` | 命令自动挂载 |
