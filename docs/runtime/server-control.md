# Server Control 与状态文件

`pms\program\swooleHttp\SwooleHttpServerControl` 是 Swoole HTTP 服务的进程状态与控制工具类。它不启动业务逻辑，只负责记录服务状态、识别进程、发送信号和触发 restart。

## 命令常量

```php
public const SERVER_COMMAND = 'swoole-http-server';
public const CONTROL_COMMAND = 'swoole-http-control';
```

进程匹配依赖 `SERVER_COMMAND` 是否出现在 `ps -p <pid> -o command=` 输出中。

## 文件路径

| 方法 | 路径 |
| --- | --- |
| `stateFile()` | `Path::getRuntime('/interpreter/pid/swoole-http.json')` |
| `pidFile()` | `Path::getRuntime('/interpreter/pid/swoole-http.pid')` |

状态文件由本包维护，PID 文件由 Swoole settings 中的 `pid_file` 指向。

## 状态写入

`writeState(array $state, bool $replace = false)` 会：

1. 确保状态目录存在
2. 在 `replace=false` 时合并旧状态
3. 追加 `state_file`、`pid_file`、`updated_at`、`updated_at_text`
4. 用 `file_put_contents(..., LOCK_EX)` 写入 JSON

`replace=true` 用于服务 `start` 阶段重建完整状态；reload 等增量事件使用默认合并模式。

## 状态读取

`readState()` 在文件不存在、内容为空或 JSON 无效时返回空数组。

调用方应把空数组视为“没有可用运行状态”，而不是服务一定未运行；`status()` 会进一步清理孤儿 PID 文件。

## status

`status()` 的判断链：

1. 读取状态文件
2. 状态为空时尝试 `cleanupOrphanPidFile()`
3. 取 `master_pid`
4. 读取进程命令
5. 用 `has_process($pid)` 判断进程是否存在
6. 用 `isSwooleHttpProcessCommand($command)` 判断命令是否匹配
7. 如果 PID 存在但命令不匹配，删除本服务状态

返回结构包含：

- `running`：进程存在且命令匹配
- `alive`：PID 存在
- `matched`：命令匹配
- `master_pid`
- `manager_pid`
- `process_command`
- `state`

## reload

`reload()` 要求 `status()['running']` 为真，然后向 master PID 发送 `SIGUSR1`。

失败时返回：

```json
{
  "ok": false,
  "message": "swoole-http 服务未运行或 PID 不匹配",
  "status": {}
}
```

## stop

`stop()` 要求服务正在运行，然后向 master PID 发送 `SIGTERM`。

它只发送信号，不同步等待进程完全退出。需要等待释放端口的逻辑在 `restart()` 中实现。

## restart

`restart()` 的主流程：

1. 校验服务正在运行
2. 从状态文件恢复启动命令
3. 调用 `stop()`
4. 等待 master PID、manager PID 和监听端口释放
5. 通过 `call_php_script($root, $startCommand, $logPath)` 后台拉起服务

等待时间为：

```php
max(10, (int)($state['settings']['max_wait_time'] ?? 10) + 5)
```

如果旧进程或端口未释放，restart 返回失败。

## 启动命令恢复

`restartStartCommand()` 优先使用状态中的 `start_argv`，并通过 `withDaemonizeArgv()` 移除旧 `--daemonize` 参数后追加：

```bash
--daemonize 1
```

如果 `start_argv` 不存在，则退回到 `start_command` 字符串并追加后台运行参数。

这意味着 restart 触发的新进程默认以 daemonize 模式运行。

## 平台限制

当前进程识别和端口检测依赖类 Unix 工具：

- `ps`
- `lsof`
- `posix_kill`

`processCommand()` 和 `isTcpListenBusy()` 在 `PHP_OS === 'WINNT'` 时返回空或 false，因此 Windows 下不应依赖完整的进程匹配和端口释放检查。
