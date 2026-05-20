<?php

namespace pms\program\swooleHttp;

use pms\app\TerminalCommandApp;
use Throwable;

class SwooleHttpControlCommand extends TerminalCommandApp
{
    protected string $name = SwooleHttpServerControl::CONTROL_COMMAND;
    protected string $description = '控制 swoole-http 服务';

    protected array $validate = [
        'action' => [
            'type' => COMMAND_ARGUMENT_TYPE,
            'des' => '控制动作: status|reload|stop|restart',
            'default' => '',
        ],
        'delay' => [
            'type' => COMMAND_OPTION_TYPE,
            'des' => '延迟执行秒数',
            'default' => 0,
        ],
    ];

    public function entry(): void
    {
        $delay = max(0, (int)$this->input->getOption('delay'));
        if ($delay > 0) {
            sleep($delay);
        }
        try {
            $action = strtolower((string)$this->input->getArgument('action'));
            $result = match ($action) {
                'status' => [
                    'ok' => true,
                    'message' => 'status',
                    'status' => SwooleHttpServerControl::status(),
                ],
                'reload' => SwooleHttpServerControl::reload(),
                'stop' => SwooleHttpServerControl::stop(),
                'restart' => SwooleHttpServerControl::restart(),
                default => [
                    'ok' => false,
                    'message' => '未知控制动作',
                    'allowed' => ['status', 'reload', 'stop', 'restart'],
                ],
            };
        } catch (Throwable $throwable) {
            $result = [
                'ok' => false,
                'message' => $throwable->getMessage(),
                'file' => $throwable->getFile(),
                'line' => $throwable->getLine(),
            ];
        }
        $this->output::writeJsonStrLn($result);
        if (!($result['ok'] ?? false)) {
            exit(1);
        }
    }
}
