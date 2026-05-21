# 配置项参考

本包读取的配置集中在 `http.web_root` 和 `http.swoole.*`。

## http.web_root

默认值：

```php
config('http.web_root', '/public')
```

用途：

- 作为未传 `--root` 时的 WebRoot
- 最终通过 `Path::mount('WebRoot', $webRoot)` 挂载
- 被 `interpreter-http` 的静态文件输出逻辑使用

命令行 `--root` 优先级高于配置。

## http.swoole.host

默认值：

```php
config('http.swoole.host', '127.0.0.1')
```

用途：传给 `new Swoole\Http\Server($host, $port, SWOOLE_PROCESS)`。

命令行 `--host` 优先级高于配置。

## http.swoole.port

默认值：

```php
config('http.swoole.port', 9999)
```

用途：传给 `new Swoole\Http\Server($host, $port, SWOOLE_PROCESS)`。

命令行 `--port` 优先级高于配置。

## http.swoole.config

默认值：

```php
config('http.swoole.config', [])
```

用途：作为 Swoole server settings 的扩展配置传给 `$http->set($settings)`。

当前最终 settings 结构：

```php
[
    'log_file' => Path::getRuntime('/interpreter/log/swoole-http.log'),
    'pid_file' => SwooleHttpServerControl::pidFile(),
    'max_wait_time' => 10,
    ...$setConfig,
    'reload_async' => true,
    'enable_coroutine' => true,
    'daemonize' => $daemonize,
]
```

覆盖规则：

- `http.swoole.config` 可以覆盖前面的 `log_file`、`pid_file`、`max_wait_time`
- 但当前代码会在展开后再次写入 `reload_async`、`enable_coroutine`、`daemonize`
- 因此这三个键以命令实现为准

## 命令行选项优先级

| 运行参数 | 优先级 |
| --- | --- |
| host | `--host` > `http.swoole.host` > `127.0.0.1` |
| port | `--port` > `http.swoole.port` > `9999` |
| root | `--root` > `http.web_root` > `/public` |
| daemonize | `--daemonize` 的布尔解析结果 |

`--daemonize` 通过 `filter_var(..., FILTER_VALIDATE_BOOLEAN)` 解析。

## runtime 文件

| 类型 | 路径 |
| --- | --- |
| Swoole log | `runtime/interpreter/log/swoole-http.log` |
| PID file | `runtime/interpreter/pid/swoole-http.pid` |
| state file | `runtime/interpreter/pid/swoole-http.json` |
| restart log | `runtime/interpreter/log/swoole-http.restart.<YmdHis>.log` |

实际路径由 `Path::getRuntime()` 解析。

## 配置示例

```php
return [
    'web_root' => '/public',
    'swoole' => [
        'host' => '127.0.0.1',
        'port' => 9999,
        'config' => [
            'worker_num' => 4,
            'max_request' => 10000,
            'max_wait_time' => 10,
        ],
    ],
];
```

具体配置文件位置由框架配置加载规则决定，本包只读取 `config()` 中已经可用的值。
