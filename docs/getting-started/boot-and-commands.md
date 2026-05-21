# Boot 挂载与启动命令

## 命令注册

`bin/autorun.php` 使用 `TerminalCommandHook::mount()` 注册两个命令类：

| 命令类 | 命令名 | 职责 |
| --- | --- | --- |
| `SwooleHttpCommand` | `swoole-http-server` | 启动 Swoole HTTP Server |
| `SwooleHttpControlCommand` | `swoole-http-control` | 控制已有 Swoole HTTP Server |

注册逻辑只在 `TerminalCommandHook` 类存在时执行，这使本包保持在 terminal interpreter 之上接入，而不是自己创建新的命令入口系统。

## 启动命令

```bash
php pms swoole-http-server
```

可用选项：

| 选项 | 来源 | 说明 |
| --- | --- | --- |
| `--host` | 命令选项，缺省读 `http.swoole.host` | 监听地址 |
| `--port` | 命令选项，缺省读 `http.swoole.port` | 监听端口 |
| `--root` | 命令选项，缺省读 `http.web_root` | Web 根目录 |
| `--daemonize` | 命令选项 | 是否守护进程运行 |

示例：

```bash
php pms swoole-http-server --host 0.0.0.0 --port 9501 --root public
php pms swoole-http-server --daemonize 1
```

`--root` 支持两种模式：

- 命令行显式传入绝对路径时，直接规范化为该路径
- 其他情况通过 `Path::getRoot($root)` 解析为项目根目录下路径

解析后的路径会挂载为 `Path::mount('WebRoot', $webRoot)`，供 HTTP sandbox 的静态文件逻辑使用。

## 控制命令

```bash
php pms swoole-http-control status
php pms swoole-http-control reload
php pms swoole-http-control stop
php pms swoole-http-control restart
```

`swoole-http-control` 接收一个必填参数：

| 参数 | 说明 |
| --- | --- |
| `status` | 输出当前状态 JSON |
| `reload` | 向 master 进程发送 `SIGUSR1` |
| `stop` | 向 master 进程发送 `SIGTERM` |
| `restart` | 停止当前进程，等待释放后重新拉起 |

可选参数：

| 选项 | 说明 |
| --- | --- |
| `--delay` | 延迟执行秒数，默认 `0` |

控制命令固定输出 JSON。`ok=false` 时命令以 `exit(1)` 结束，方便脚本判断失败。

## 最小开发接入

开发本包时，建议先从命令是否被挂载开始验证：

1. 确认 Composer autoload 已加载 `bin/autoload.php`
2. 确认 `TerminalCommandHook` 存在
3. 确认 `php pms` 命令列表或直接执行命令能找到 `swoole-http-server`
4. 启动服务后检查 `runtime/interpreter/pid/swoole-http.json`
5. 访问监听端口，确认请求进入 `interpreter-http` 的 sandbox 链

如果命令不存在，优先查 autoload 和 terminal interpreter；如果命令存在但请求不通，再查 Swoole 扩展、监听地址、端口占用和 HTTP sandbox 路由。
