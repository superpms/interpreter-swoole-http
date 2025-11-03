<?php

namespace pms\program\swooleHttp;

use pms\annotate\Inject;
use pms\app\TerminalCommandApp;
use pms\facade\Path;
use pms\hook\SwooleHttpLifecycleHook;
use pms\inject\TerminalInputInject;
use pms\inject\TerminalOutputInject;
use pms\interpreter\swooleHttp\Sandbox;
use pms\interpreter\swooleHttp\sandbox\SwooleHttpRequest;
use pms\interpreter\swooleHttp\sandbox\SwooleHttpResponse;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;

class SwooleHttpCommand extends TerminalCommandApp{

    protected string $name = "swoole-http-server";
    protected string $description = "启动 swoole-http 服务";

    #[Inject(TerminalInputInject::class)]
    protected TerminalInputInject $input;

    #[Inject(TerminalOutputInject::class)]
    protected TerminalOutputInject $output;


    public function entry(){
        $root = config('http.root','public');
        Path::mount('WebRoot',$root);
        $host = config('http.swoole.host', '127.0.0.1');
        $port = config('http.swoole.port', 9999);
        $setConfig = config('http.swoole.config', []);
        if (!is_array($setConfig)) {
            $setConfig = [];
        }
        SwooleHttpLifecycleHook::run(LIFECYCLE_BOOT);
        $http = new Server($host, $port);
        $http->set([
            'log_file' => Path::getRuntime('/interpreter/log/swoole-http.log'),
            ...$setConfig,
            'reload_async'=>true,
        ]);
        $output = $this->output;
        $http->on('start', function (Server $server) use ($output, $host, $port) {
            $output->writeArrayBlock([
                $output->setBoldStr($output->setColorStr(TERMINAL_COLOR_GREEN, "● PHP Swoole-Http 服务器")),
                '服务IP: ' . $host,
                '服务端口: ' . $port,
                sprintf('本机访问地址: <http://127.0.0.1:%s/>', $port),
                "\033[31m使用\033[1m`CTRL-C`\033[22m即可退出服务\033[0m",
            ]);
        });
        $http->on('request', function (Request $request, Response $response) {
            $this->customShutDownHandler($response);
            $this->initVarDumper($response);
            $myRequest = new SwooleHttpRequest($request);
            $myResponse = new SwooleHttpResponse($response);
            SwooleHttpLifecycleHook::run(LIFECYCLE_SANDBOX_BOOTED, $myRequest,$myResponse);
            $exp = new Sandbox($myRequest, $myResponse,$this->bootOptions);
            $exp->run();
            SwooleHttpLifecycleHook::run(LIFECYCLE_SANDBOX_RAN);
        });
        SwooleHttpLifecycleHook::run(LIFECYCLE_SERVER_BOOTED, $http);
        $http->start();
    }


    public function customShutDownHandler($response): void{
        register_shutdown_function(function ()use($response) {
            $error = error_get_last();
            if (!empty($error)) {
                swoole_clear_error();
                $response->status(500, 'Server Error');
                if ($this->bootOptions->error_debug) {
                    $response->header("content-type", JSON_CONTENT_TYPE);
                    $response->end(json_encode($error));
                } else {
                    $response->end();
                }
            }
        });
    }

    public function initVarDumper(Response $response): void{
        $cloner = new \Symfony\Component\VarDumper\Cloner\VarCloner();
        $cloner->addCasters(\Symfony\Component\VarDumper\Caster\ReflectionCaster::UNSET_CLOSURE_FILE_INFO);
        $dumper = new \Symfony\Component\VarDumper\Dumper\HtmlDumper();
        \Symfony\Component\VarDumper\VarDumper::setHandler(function ($var, $label = null) use ($response,$cloner,$dumper) {
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