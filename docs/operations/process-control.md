# 进程控制

本包提供两个命令：

- `swoole-http-server`：启动服务
- `swoole-http-control`：控制服务

## start

启动命令：

```bash
php pms swoole-http-server --host 127.0.0.1 --port 9999 --root public
```

后台启动：

```bash
php pms swoole-http-server --daemonize 1
```

启动成功后，`start` 事件会写入状态文件和 PID 文件。控制命令依赖这些文件判断服务状态。

## status

```bash
php pms swoole-http-control status
```

返回 JSON，核心字段：

| 字段 | 说明 |
| --- | --- |
| `ok` | 控制命令是否执行成功 |
| `message` | 固定为 `status` |
| `status.running` | 进程存在且命令匹配 |
| `status.alive` | master PID 仍存在 |
| `status.matched` | 进程命令包含 `swoole-http-server` |
| `status.master_pid` | master PID |
| `status.manager_pid` | manager PID |
| `status.process_command` | 当前 PID 对应命令 |
| `status.state` | 状态文件内容 |

`status()` 会在状态缺失时清理孤儿 PID 文件；如果 PID 存在但命令不匹配，也会删除本服务状态，避免误控其他进程。

## reload

```bash
php pms swoole-http-control reload
```

向 master PID 发送 `SIGUSR1`。

Swoole server 的 `beforeReload` / `afterReload` 回调会更新状态文件中的 reload 时间和 manager PID。

## stop

```bash
php pms swoole-http-control stop
```

向 master PID 发送 `SIGTERM`。

该命令只负责发信号。状态清理在 Swoole server `shutdown` 回调中完成，且只有 master PID 匹配时才会删除状态。

## restart

```bash
php pms swoole-http-control restart
```

restart 会：

1. 检查当前服务是否运行
2. 读取状态中的原始启动参数
3. 发送 stop 信号
4. 等待 master、manager 和监听端口释放
5. 以 `--daemonize 1` 重新拉起服务
6. 把拉起日志写入 `runtime/interpreter/log/swoole-http.restart.<YmdHis>.log`

如果旧进程或端口没有在等待时间内释放，restart 返回 `ok=false`。

## delay

控制命令支持：

```bash
php pms swoole-http-control reload --delay 3
```

`--delay` 在执行动作前 sleep 指定秒数。它适合脚本化控制，不改变服务内部状态。

## PID 与端口匹配

当前实现使用：

- `has_process($pid)` 判断进程存在
- `ps -p <pid> -o command=` 读取命令
- `lsof -nP -iTCP:<port> -sTCP:LISTEN` 判断监听端口占用

命令匹配以进程命令包含 `swoole-http-server` 为准。

## 常见失败判断

| 现象 | 优先检查 |
| --- | --- |
| `status.running=false` | 状态文件、PID 是否存在，进程命令是否匹配 |
| `reload` 失败 | `posix_kill` 是否可用，master PID 是否存在 |
| `stop` 后状态仍在 | Swoole `shutdown` 是否执行，master PID 是否匹配 |
| `restart` 超时 | master / manager 是否仍存在，端口是否仍被占用 |
| restart 后没有服务 | restart 日志文件，`start_command` / `start_argv` 是否完整 |
