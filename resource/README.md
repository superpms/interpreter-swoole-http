# Swoole HTTP 配置模板

Composer extra.pms.config 将 resource/config.php 安装为宿主配置目录中的 http-swoole.php：

```json
"config": {
    "http-swoole": "resource/config.php"
}
```

文件直接返回 host、port、config。启动命令通过全局 config() 读取配置命名空间：

```php
$swooleConfig = config('http-swoole', []);
```

模板取自宿主实际设置：host=127.0.0.1、port=9500、worker_num=10、reload_async=true、max_wait_time=10。CLI host、port 参数优先。已有 http-swoole.php 时安装钩子保留宿主文件。

HTTP 基础配置及中间件模板由 interpreter-http 依赖安装。安装 interpreter-mcp-http 后，Swoole 共用 HTTP Sandbox 接收 /mcp 根入口。宿主根项目 post-autoload-dump 执行 @php pms vendor:install:hook。
