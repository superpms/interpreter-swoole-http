# Swoole HTTP 配置文件

Swoole 服务配置位于宿主配置目录的 `http-swoole.php`，文件直接返回：

```php
return [
    'host' => '127.0.0.1',
    'port' => 9500,
    'config' => [
        'worker_num' => 10,
        'reload_async' => true,
        'max_wait_time' => 10,
    ],
];
```

配置加载器以 `http-swoole` 为配置命名空间。启动命令通过全局 `config()` 读取：

```php
$swooleConfig = config('http-swoole', []);
```

| 字段 | 用途 | 命令内置默认值 |
| --- | --- | --- |
| host | Swoole 监听地址 | 127.0.0.1 |
| port | Swoole 监听端口 | 9999 |
| config | 传给 Swoole Server::set() 的设置 | [] |

安装模板的端口为 9500。已有 `http-swoole.php` 时安装钩子保留宿主文件。Composer `extra.pms.config` 登记 `"http-swoole": "resource/config.php"`，由宿主根项目 post-autoload-dump 执行 `@php pms vendor:install:hook`。

## WebRoot

WebRoot 共用 HTTP 配置 `config('http.web_root', '/public')`，命令行 `--root` 优先，最终通过 Path::mount('WebRoot', $webRoot) 挂载。

## 设置合并

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

`$setConfig` 来自 `$swooleConfig['config'] ?? []`。它可以覆盖前面的 log_file、pid_file、max_wait_time。reload_async、enable_coroutine、daemonize 以命令实现为准。

## 命令行优先级

| 参数 | 优先级 |
| --- | --- |
| host | --host > http-swoole.php 中的 host > 127.0.0.1 |
| port | --port > http-swoole.php 中的 port > 9999 |
| root | --root > http.web_root > /public |
| daemonize | --daemonize 经 FILTER_VALIDATE_BOOLEAN 解析的结果 |

## 运行文件

| 类型 | 路径 |
| --- | --- |
| 日志 | runtime/interpreter/log/swoole-http.log |
| PID | runtime/interpreter/pid/swoole-http.pid |
| 状态 | runtime/interpreter/pid/swoole-http.json |
| 重启日志 | runtime/interpreter/log/swoole-http.restart.<YmdHis>.log |

实际路径由 Path::getRuntime() 解析。
