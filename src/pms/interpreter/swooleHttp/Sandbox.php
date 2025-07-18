<?php
namespace pms\interpreter\swooleHttp;

use pms\hook\SwooleHttpLifecycleHook;
use pms\interpreter\http\Sandbox as HttpSandbox;

class Sandbox extends HttpSandbox {

    public function __destruct(){
        SwooleHttpLifecycleHook::run(SWOOLE_HTTP_LIFECYCLE_REQUEST_DESTRUCT);
    }
}