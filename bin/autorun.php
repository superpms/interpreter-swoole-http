<?php
namespace pms;

use pms\hook\TerminalCommandHook;

if(class_exists('pms\hook\TerminalCommandHook')){
    TerminalCommandHook::mount(
        'swoole-http-server',
        program\swooleHttp\SwooleHttpCommand::class
    );
}