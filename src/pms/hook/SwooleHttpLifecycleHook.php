<?php

namespace pms\hook;

use pms\contract\HookInterface;

class SwooleHttpLifecycleHook implements HookInterface
{

    public static array $container = [
        SWOOLE_HTTP_LIFECYCLE_START => [],
        SWOOLE_HTTP_LIFECYCLE_SERVER_INIT => [],
        SWOOLE_HTTP_LIFECYCLE_REQUEST_ON => [],
        SWOOLE_HTTP_LIFECYCLE_REQUEST_AFTER => [],
        SWOOLE_HTTP_LIFECYCLE_REQUEST_DESTRUCT => [],
    ];

    public static function mount(string $lifecycle, \Closure $closure): bool
    {
        if (array_key_exists($lifecycle, self::$container)) {
            self::$container[$lifecycle][] = $closure;
            return true;
        }
        return false;
    }

    public static function run(string $lifecycle, ...$args){
        if (array_key_exists($lifecycle, self::$container)) {
            foreach (self::$container[$lifecycle] as $closure) {
                $closure(...$args);
            }
        }
    }
}