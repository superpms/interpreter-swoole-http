<?php

namespace pms\program\swooleHttp;

use pms\app\TerminalCommandApp;
use pms\facade\BootOptions;
use pms\facade\Ctx;
use pms\facade\Path;
use pms\hook\HttpLifecycleHook;
use pms\inject\HttpRequestInject;
use pms\interpreter\http\Sandbox;
use pms\interpreter\swooleHttp\sandbox\SwooleHttpRequest;
use pms\interpreter\swooleHttp\sandbox\SwooleHttpResponse;
use Throwable;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;
use Symfony\Component\VarDumper\Caster\ReflectionCaster;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Symfony\Component\VarDumper\VarDumper;

class SwooleHttpCommand extends TerminalCommandApp
{

    protected const DUMPER_RESPONSE_KEY = 'swoole_http_response';

    protected string $name = "swoole-http-server";
    protected string $description = "启动 swoole-http 服务";

    protected array $validate = [
        'host' => [
            'type' => COMMAND_OPTION_TYPE,
            'des' => '绑定地址',
            'default' => ''
        ],
        'port' => [
            'type' => COMMAND_OPTION_TYPE,
            'des' => '绑定端口',
            'default' => ''
        ],
        'root' => [
            'type' => COMMAND_OPTION_TYPE,
            'des' => 'Web根目录',
            'default' => ''
        ],
        'daemonize' => [
            'type' => COMMAND_OPTION_TYPE,
            'des' => '是否以守护进程模式运行',
            'default' => ''
        ],
    ];

    public function entry()
    {
        HttpLifecycleHook::run(LIFECYCLE_BOOT);
        $rootOption = $this->input->getOption('root');
        $root = $rootOption ?: config('http.web_root', '/public');
        $webRoot = $this->resolveWebRoot($root, !empty($rootOption));
        Path::mount('WebRoot', $webRoot);
        $host = $this->input->getOption('host') ?: config('http.swoole.host', '127.0.0.1');
        $port = (int)($this->input->getOption('port') ?: config('http.swoole.port', 9999));
        $setConfig = config('http.swoole.config', []);
        if (!is_array($setConfig)) {
            $setConfig = [];
        }
        $daemonize = filter_var($this->input->getOption('daemonize'), FILTER_VALIDATE_BOOLEAN);
        HttpLifecycleHook::run(LIFECYCLE_BOOTED);
        $this->initVarDumper();
        $http = new Server($host, $port, SWOOLE_PROCESS);
        $settings = [
            'log_file' => Path::getRuntime('/interpreter/log/swoole-http.log'),
            'pid_file' => SwooleHttpServerControl::pidFile(),
            'max_wait_time' => 10,
            ...$setConfig,
            'reload_async' => true,
            'enable_coroutine' => true,
            'daemonize' => $daemonize,
        ];
        $http->set($settings);
        $output = $this->output;
        $startArgv = $_SERVER['argv'] ?? ['pms', 'swoole-http-server'];
        $startCommand = SwooleHttpServerControl::startCommand($startArgv);
        $http->on('start', function (Server $server) use ($output, $host, $port, $webRoot, $settings, $startCommand, $startArgv) {
            SwooleHttpServerControl::writeState([
                'host' => $host,
                'port' => $port,
                'web_root' => $webRoot,
                'root_path' => Path::getRoot(),
                'php_binary' => PHP_BINARY,
                'command_binary' => (string)($_SERVER['argv'][0] ?? PHP_BINARY),
                'start_command' => $startCommand,
                'start_argv' => $startArgv,
                'master_pid' => $server->master_pid,
                'manager_pid' => $server->manager_pid,
                'settings' => $settings,
                'started_at' => time(),
                'started_at_text' => date('Y-m-d H:i:s'),
                'reload_count' => 0,
            ], true);
            $output->writeArrayBlock([
                $output->setBoldStr($output->setColorStr(TERMINAL_COLOR_GREEN, "● PHP Swoole-Http 服务器")),
                '服务IP: ' . $host,
                '服务端口: ' . $port,
                '服务根目录: ' . $webRoot,
                '运行模式: SWOOLE_PROCESS',
                sprintf('本机访问地址: <http://127.0.0.1:%s/>', $port),
                "\033[31m使用\033[1m`CTRL-C`\033[22m即可退出服务\033[0m",
            ]);
        });
        $http->on('beforeReload', function () {
            $state = SwooleHttpServerControl::readState();
            SwooleHttpServerControl::writeState([
                'before_reload_at' => time(),
                'before_reload_at_text' => date('Y-m-d H:i:s'),
                'reload_count' => (int)($state['reload_count'] ?? 0) + 1,
            ]);
        });
        $http->on('afterReload', function (Server $server) {
            SwooleHttpServerControl::writeState([
                'manager_pid' => $server->manager_pid,
                'after_reload_at' => time(),
                'after_reload_at_text' => date('Y-m-d H:i:s'),
            ]);
        });
        $http->on('shutdown', function (Server $server) {
            SwooleHttpServerControl::removeStateIfMaster($server->master_pid);
        });
        $http->on('request', function (Request $request, Response $response) {
            pms_error_clear();
            Ctx::set(HttpRequestInject::class, null);
            Ctx::set(static::DUMPER_RESPONSE_KEY, $response);
            try {
                $myRequest = new SwooleHttpRequest($request);
                $myResponse = new SwooleHttpResponse($response);
                $exp = new Sandbox($myRequest, $myResponse);
                $exp->run();
            } catch (Throwable $e) {
                $this->handleRequestThrowable($e, $response);
            } finally {
                Ctx::set(HttpRequestInject::class, null);
                Ctx::set(static::DUMPER_RESPONSE_KEY, null);
                pms_error_clear();
            }
        });
        HttpLifecycleHook::run(LIFECYCLE_SERVER_BOOTED, $http);
        $http->start();
    }

    protected function resolveWebRoot(string $root, bool $allowAbsolute = false): string
    {
        if ($allowAbsolute && str_starts_with($root, DIRECTORY_SEPARATOR)) {
            return path_join($root);
        }
        return Path::getRoot($root);
    }

    protected function handleRequestThrowable(Throwable $e, Response $response): void
    {
        if (!$response->isWritable()) {
            return;
        }
        $response->status(500, 'Server Error');
        $response->header('content-type', JSON_CONTENT_TYPE);
        if (BootOptions::get_error_debug()) {
            $response->end(json_encode([
                'message' => $e->getMessage(),
                'code' => 500,
                'error' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTrace(),
            ]));
        } else {
            $response->end(json_encode([
                'message' => '系统内部错误',
                'code' => 500
            ]));
        }
    }

    protected function initVarDumper(): void
    {
        static $initialized = false;
        if ($initialized) {
            return;
        }
        $initialized = true;
        $cloner = new VarCloner();
        $cloner->addCasters(ReflectionCaster::UNSET_CLOSURE_FILE_INFO);
        $dumper = new HtmlDumper();
        VarDumper::setHandler(function ($var, $label = null) use ($cloner, $dumper) {
            $response = Ctx::get(static::DUMPER_RESPONSE_KEY);
            if (!$response instanceof Response || !$response->isWritable()) {
                return;
            }
            $var = $cloner->cloneVar($var)?->withContext(['label' => $label]);
            ob_start();
            $response->status(500, 'Server Error');
            $dumper->dump($var);
            $output = ob_get_clean();
            $response->header('Content-Type', "text/html");
            $response->write($output);
        });
    }

}
