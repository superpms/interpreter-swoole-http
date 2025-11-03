<?php

namespace pms\hook;

use pms\app\LifecycleHookApp;

class SwooleHttpLifecycleHook extends LifecycleHookApp
{

    public static array $container = [
        LIFECYCLE_BOOT => [],
        SWOOLE_LIFECYCLE_SERVER_START => [],
        SWOOLE_LIFECYCLE_HTTP_REQUEST_ON => [],
        SWOOLE_LIFECYCLE_HTTP_REQUEST_AFTER => [],
        SWOOLE_LIFECYCLE_HTTP_REQUEST_DESTRUCT => [],
    ];

}