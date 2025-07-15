<?php

namespace pms\interpreter\swooleHttp\sandbox;

use pms\interpreter\http\sandbox\HttpRequest;
use Swoole\Http\Request;

class SwooleHttpRequest extends HttpRequest {

    protected Request $request;

    protected function platformConstruct(...$args): void
    {
        /**
         * @var Request $request
         */
        $request = $args[0];
        $this->request = $request;
        $this->server = $request->server;
    }

    protected function platformInit(): void
    {
        $this->header = $this->request->header;
        ksort($this->server);
        $this->cookie = $this->request->cookie ?? [];
        $this->get = $this->request->get ?? [];
        $this->post = $this->request->post ?? [];
        $this->files = $this->request->files ?? [];
        $this->input = $this->request->getContent() ?? "";
    }

}