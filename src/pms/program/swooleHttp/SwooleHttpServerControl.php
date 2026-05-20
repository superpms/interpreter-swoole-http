<?php

namespace pms\program\swooleHttp;

use pms\facade\Path;

class SwooleHttpServerControl
{
    public const SERVER_COMMAND = 'swoole-http-server';
    public const CONTROL_COMMAND = 'swoole-http-control';

    public static function stateFile(): string
    {
        return Path::getRuntime('/interpreter/pid/swoole-http.json');
    }

    public static function pidFile(): string
    {
        return Path::getRuntime('/interpreter/pid/swoole-http.pid');
    }

    public static function writeState(array $state, bool $replace = false): bool
    {
        $file = static::stateFile();
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $state = [
            ...($replace ? [] : static::readState()),
            ...$state,
            'state_file' => $file,
            'pid_file' => static::pidFile(),
            'updated_at' => time(),
            'updated_at_text' => date('Y-m-d H:i:s'),
        ];
        return file_put_contents($file, json_encode($state, 320), LOCK_EX) !== false;
    }

    public static function readState(): array
    {
        $file = static::stateFile();
        if (!is_file($file)) {
            return [];
        }
        $content = file_get_contents($file);
        if ($content === false || $content === '') {
            return [];
        }
        $state = json_decode($content, true);
        return is_array($state) ? $state : [];
    }

    public static function removeStateIfMaster(int $masterPid): void
    {
        $state = static::readState();
        if ((int)($state['master_pid'] ?? 0) !== $masterPid) {
            return;
        }
        $stateFile = static::stateFile();
        if (is_file($stateFile)) {
            unlink($stateFile);
        }
        static::removePidFileIfMaster($masterPid);
    }

    public static function startCommand(array $argv): string
    {
        $parts = [PHP_BINARY, ...$argv];
        return implode(' ', array_map('escapeshellarg', $parts));
    }

    public static function controlCommand(string $action, int $delay = 0, ?array $state = null): string
    {
        $state ??= static::readState();
        $binary = (string)($state['php_binary'] ?? PHP_BINARY);
        $parts = [$binary, 'pms', static::CONTROL_COMMAND, $action];
        if ($delay > 0) {
            $parts[] = '--delay';
            $parts[] = (string)$delay;
        }
        return implode(' ', array_map('escapeshellarg', $parts));
    }

    public static function status(): array
    {
        $state = static::readState();
        if (empty($state)) {
            static::cleanupOrphanPidFile();
        }
        $masterPid = (int)($state['master_pid'] ?? 0);
        $command = $masterPid > 0 ? static::processCommand($masterPid) : '';
        $alive = $masterPid > 0 && has_process($masterPid);
        $matched = $alive && static::isSwooleHttpProcessCommand($command);
        if ($masterPid > 0 && !$matched) {
            static::removeStateIfMaster($masterPid);
            $state = [];
            $masterPid = 0;
            $command = '';
            $alive = false;
            $matched = false;
        }
        return [
            'running' => $matched,
            'alive' => $alive,
            'matched' => $matched,
            'master_pid' => $masterPid,
            'manager_pid' => (int)($state['manager_pid'] ?? 0),
            'process_command' => $command,
            'state' => $state,
        ];
    }

    public static function reload(): array
    {
        $status = static::status();
        if (!$status['running']) {
            return [
                'ok' => false,
                'message' => 'swoole-http 服务未运行或 PID 不匹配',
                'status' => $status,
            ];
        }
        $ok = static::sendSignal((int)$status['master_pid'], SIGUSR1);
        return [
            'ok' => $ok,
            'message' => $ok ? 'reload 信号已发送' : 'reload 信号发送失败',
            'status' => $status,
        ];
    }

    public static function stop(): array
    {
        $status = static::status();
        if (!$status['running']) {
            return [
                'ok' => false,
                'message' => 'swoole-http 服务未运行或 PID 不匹配',
                'status' => $status,
            ];
        }
        $ok = static::sendSignal((int)$status['master_pid'], SIGTERM);
        return [
            'ok' => $ok,
            'message' => $ok ? 'stop 信号已发送' : 'stop 信号发送失败',
            'status' => $status,
        ];
    }

    public static function restart(): array
    {
        $status = static::status();
        if (!$status['running']) {
            return [
                'ok' => false,
                'message' => 'swoole-http 服务未运行或 PID 不匹配',
                'status' => $status,
            ];
        }
        $state = $status['state'];
        $startCommand = static::restartStartCommand($state);
        if ($startCommand === '') {
            return [
                'ok' => false,
                'message' => '缺少 swoole-http 启动命令，无法重启',
                'status' => $status,
            ];
        }
        $stop = static::stop();
        if (!($stop['ok'] ?? false)) {
            return $stop;
        }
        $masterPid = (int)$status['master_pid'];
        $managerPid = (int)$status['manager_pid'];
        $timeout = max(10, (int)($state['settings']['max_wait_time'] ?? 10) + 5);
        $deadline = time() + $timeout;
        while (time() < $deadline) {
            $masterAlive = has_process($masterPid);
            $managerAlive = $managerPid > 0 && has_process($managerPid);
            $portBusy = static::isTcpListenBusy((int)($state['port'] ?? 0), (string)($state['host'] ?? ''));
            if (!$masterAlive && !$managerAlive && !$portBusy) {
                break;
            }
            usleep(200000);
        }
        if (has_process($masterPid) || ($managerPid > 0 && has_process($managerPid)) || static::isTcpListenBusy((int)($state['port'] ?? 0), (string)($state['host'] ?? ''))) {
            return [
                'ok' => false,
                'message' => '旧 swoole-http 进程或监听端口未在等待时间内释放',
                'status' => static::status(),
            ];
        }
        $root = (string)($state['root_path'] ?? Path::getRoot());
        $logPath = Path::getRuntime('/interpreter/log/swoole-http.restart.' . date('YmdHis') . '.log');
        call_php_script($root, $startCommand, $logPath);
        return [
            'ok' => true,
            'message' => 'restart 已触发',
            'start_command' => $startCommand,
            'log_file' => $logPath,
        ];
    }

    public static function processCommand(int $pid): string
    {
        if ($pid <= 0) {
            return '';
        }
        if (PHP_OS === 'WINNT') {
            return '';
        }
        $command = shell_exec('ps -p ' . $pid . ' -o command= 2>/dev/null');
        return trim((string)$command);
    }

    protected static function isSwooleHttpProcessCommand(string $command): bool
    {
        return $command !== '' && str_contains($command, static::SERVER_COMMAND);
    }

    protected static function sendSignal(int $pid, int $signal): bool
    {
        if ($pid <= 0 || !function_exists('posix_kill')) {
            return false;
        }
        return posix_kill($pid, $signal);
    }

    protected static function isTcpListenBusy(int $port, string $host = ''): bool
    {
        if ($port <= 0 || PHP_OS === 'WINNT') {
            return false;
        }
        $output = shell_exec('lsof -nP -iTCP:' . $port . ' -sTCP:LISTEN 2>/dev/null');
        if (!is_string($output) || trim($output) === '') {
            return false;
        }
        if ($host === '' || $host === '0.0.0.0' || $host === '::') {
            return true;
        }
        return str_contains($output, ':' . $port) && str_contains($output, $host . ':' . $port);
    }

    protected static function restartStartCommand(array $state): string
    {
        $argv = $state['start_argv'] ?? null;
        if (is_array($argv) && !empty($argv)) {
            return static::startCommand(static::withDaemonizeArgv($argv));
        }
        $command = (string)($state['start_command'] ?? '');
        if ($command === '') {
            return '';
        }
        return $command . ' ' . escapeshellarg('--daemonize') . ' ' . escapeshellarg('1');
    }

    protected static function withDaemonizeArgv(array $argv): array
    {
        $result = [];
        $skipNext = false;
        foreach ($argv as $index => $value) {
            if ($skipNext) {
                $skipNext = false;
                continue;
            }
            if ($value === '--daemonize' || $value === '-daemonize') {
                $skipNext = isset($argv[$index + 1]) && !str_starts_with((string)$argv[$index + 1], '-');
                continue;
            }
            $result[] = $value;
        }
        $result[] = '--daemonize';
        $result[] = '1';
        return $result;
    }

    protected static function removePidFileIfMaster(int $masterPid): void
    {
        $pidFile = static::pidFile();
        if (!is_file($pidFile)) {
            return;
        }
        $pid = (int)trim((string)file_get_contents($pidFile));
        if ($pid === 0 || $pid === $masterPid) {
            unlink($pidFile);
        }
    }

    protected static function cleanupOrphanPidFile(): void
    {
        $pidFile = static::pidFile();
        if (!is_file($pidFile)) {
            return;
        }
        $pid = (int)trim((string)file_get_contents($pidFile));
        if ($pid <= 0 || !has_process($pid) || !static::isSwooleHttpProcessCommand(static::processCommand($pid))) {
            unlink($pidFile);
        }
    }
}
