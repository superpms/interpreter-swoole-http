<?php

namespace pms\hook;

use pms\app\LifecycleHookApp;

class SwooleHttpLifecycleHook extends LifecycleHookApp
{

    public static array $container = [
        LIFECYCLE_SERVER_BOOTED => [],
    ];

}