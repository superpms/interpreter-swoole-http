# 安装与自动挂载

## 安装

```bash
composer require superpms/interpreter-swoole-http
```

`composer.json` 声明了：

- `php >=8.1`
- `superpms/basic ^1.0.0`
- `superpms/interpreter-terminal ^1.0.0`
- `superpms/interpreter-http ^1.0.0`
- PSR-4：`pms\` 指向 `src/pms/`
- `autoload.files`：加载 `bin/autoload.php`

本包的 Swoole 类型提示依赖 `swoole/ide-helper` 作为开发依赖；真实运行环境仍需要安装并启用 Swoole 扩展。

## 自动加载链

Composer 载入本包后，文件链为：

```text
bin/autoload.php
├── bin/autorun.php
└── bin/const.php
```

当前 `bin/const.php` 为空文件，保留给包级常量扩展。

`bin/autorun.php` 会在 `pms\hook\TerminalCommandHook` 类存在时挂载两个终端命令：

- `pms\program\swooleHttp\SwooleHttpCommand`
- `pms\program\swooleHttp\SwooleHttpControlCommand`

因此本包依赖 `superpms/interpreter-terminal` 提供终端命令挂载点。没有终端命令系统时，本包的自动注册不会生效。

## 启动前提

运行 `swoole-http-server` 前需要满足：

- 当前进程为 CLI 模式
- PHP 已启用 Swoole 扩展
- PMS 根目录、配置目录和 runtime 目录可被框架 `Path` facade 正确解析
- 应用已安装 `superpms/interpreter-http`，因为请求执行依赖它的 `Sandbox`

`in_swoole()` 在 `superpms/basic` 中按 CLI + `SWOOLE_VERSION` 判断。其他包可以据此在 Swoole 运行时切换行为，例如数据库和 Redis 包在 Swoole 下挂载 HTTP 生命周期钩子来启用池化和自动回收。

## 包归属

本包只提供 Swoole HTTP 运行时接入：

- 命令注册
- Swoole HTTP Server 创建
- Swoole request / response 适配
- 进程状态管理
- 常驻进程下的请求级上下文清理

业务接口类、路由命中、中间件执行和响应内容序列化不在本包实现，它们继续由 `superpms/interpreter-http` 承担。
