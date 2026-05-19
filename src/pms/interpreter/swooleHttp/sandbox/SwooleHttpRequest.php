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
        $this->server = $request->server ?? [];
    }

    protected function platformInit(): void
    {
        $this->header = $this->request->header ?? [];
        ksort($this->server);
        $this->cookie = $this->request->cookie ?? [];
        $this->get = $this->request->get ?? [];
        $this->post = $this->request->post ?? [];
        $this->files = $this->request->files ?? [];
        $content = $this->request->getContent();
        $this->input = $content === false ? "" : $content;
        $this->inputLoaded = true;
    }

    public function getContent(): string|false
    {
        return $this->request->getContent();
    }

    public function rawContent(): string|false
    {
        return $this->request->rawContent();
    }

    public function getData(): string|false
    {
        return $this->request->getData();
    }

    public function getMethod(): string|false
    {
        return $this->request->getMethod();
    }

    public function parse(string $data): int|false
    {
        return $this->request->parse($data);
    }

    public function isCompleted(): bool
    {
        return $this->request->isCompleted();
    }

}
