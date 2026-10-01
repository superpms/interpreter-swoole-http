<?php

/** Swoole HTTP 独立配置，安装为宿主 http-swoole.php。 */
return [
    'host' => '127.0.0.1',
    'port' => 9500,
    'config' => [
        'worker_num' => 10,
        'reload_async' => true,
        'max_wait_time' => 10,
    ],
];
