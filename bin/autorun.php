<?php
namespace pms;

if(class_exists('pms\hook\TerminalCommandHook')){
    \pms\hook\TerminalCommandHook::mount(
        'swoole-http-server',
        program\swooleHttp\SwooleHttpCommand::class
    );
}