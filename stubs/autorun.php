<?php
namespace pms;

if(class_exists('pms\facade\TerminalCommand')){
    facade\TerminalCommand::install(
        'swoole-http-server',
        source\InterpreterSwooleHttp\command\SwooleHttpCommand::class
    );
}