<?php

namespace pms\hook;

use pms\app\LifecycleHookApp;

class SwooleHttpLifecycleHook extends LifecycleHookApp
{

    public static array $container = [
        LIFECYCLE_BOOT => [],
        LIFECYCLE_SERVER_BOOTED => [],
        LIFECYCLE_SANDBOX_BOOTED => [],
        LIFECYCLE_SANDBOX_RAN => [],
        LIFECYCLE_SANDBOX_DESTRUCT => [],
    ];

}