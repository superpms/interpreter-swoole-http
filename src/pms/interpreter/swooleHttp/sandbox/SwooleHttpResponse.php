<?php

namespace pms\interpreter\swooleHttp\sandbox;

use pms\interpreter\http\sandbox\HttpResponse;
use Swoole\Http\Response;

/**
 * @mixin Response
 */
class SwooleHttpResponse extends HttpResponse {

    private Response $response;
    public function __construct(Response $response){
        $this->response = $response;
        parent::__construct();
    }

    public function __call(string $name, array $arguments){
        return call_user_func_array([$this->response,$name],$arguments);
    }

    public function __get(string $name){
        return $this->response->$name;
    }

    public function isWritable(): bool
    {
        return $this->response->isWritable();
    }

    public function initHeader(): bool
    {
        return $this->response->initHeader();
    }

    public function cookie(string $name, string $value = '', int $expires = 0, string $path = '/', string $domain = '', bool $secure = false, bool $httponly = false, string $samesite = '', string $priority = '', bool $partitioned = false): bool
    {
        return $this->response->cookie($name,$value,$expires,$path,$domain,$secure,$httponly,$samesite,$priority,$partitioned);
    }

    public function setCookie(string $name, string $value = '', int $expires = 0, string $path = '/', string $domain = '', bool $secure = false, bool $httponly = false, string $samesite = '', string $priority = '', bool $partitioned = false): bool
    {
        return $this->response->setCookie($name,$value,$expires,$path,$domain,$secure,$httponly,$samesite,$priority,$partitioned);
    }

    public function rawcookie(string $name, string $value = '', int $expires = 0, string $path = '/', string $domain = '', bool $secure = false, bool $httponly = false, string $samesite = '', string $priority = '', bool $partitioned = false): bool
    {
        return $this->response->rawcookie($name,$value,$expires,$path,$domain,$secure,$httponly,$samesite,$priority,$partitioned);
    }

    public function setRawCookie(string $name, string $value = '', int $expires = 0, string $path = '/', string $domain = '', bool $secure = false, bool $httponly = false, string $samesite = '', string $priority = '', bool $partitioned = false): bool
    {
        return $this->response->setRawCookie($name,$value,$expires,$path,$domain,$secure,$httponly,$samesite,$priority,$partitioned);
    }

    public function status(int $http_code, string $reason = ''): bool
    {
        return $this->response->status($http_code,$reason);
    }

    public function setStatusCode(int $http_code, string $reason = ''): bool
    {
        return $this->response->setStatusCode($http_code,$reason);
    }

    public function header(string $key, array|string $value, bool $format = true): bool
    {
        return $this->response->header($key,$value,$format);
    }

    public function setHeader(string $key, array|string $value, bool $format = true): bool
    {
        return $this->response->setHeader($key,$value,$format);
    }

    public function trailer(string $key, string $value): bool
    {
        return $this->response->trailer($key,$value);
    }

    public function ping(string $data = ''): bool
    {
        return $this->response->ping($data);
    }

    public function goaway(int $error_code = 0, string $debug_data = ''): bool
    {
        return $this->response->goaway($error_code,$debug_data);
    }

    public function write(string $content): bool
    {
        return $this->response->write($content);
    }

    public function end(?string $content = null): bool
    {
        return $this->response->end($content);
    }

    public function sendfile(string $filename, int $offset = 0, int $length = 0): bool
    {
        return $this->response->sendfile($filename,$offset,$length);
    }

    public function redirect(string $location, int $http_code = 302): bool
    {
        return $this->response->redirect($location,$http_code);
    }

    public function detach(): bool
    {
        return $this->response->detach();
    }

    public function create(object|array|int $server = -1, int $fd = -1): self|false
    {
        $response = Response::create($server,$fd);
        if ($response === false) {
            return false;
        }
        return new self($response);
    }

    public function upgrade(): bool
    {
        return $this->response->upgrade();
    }

    public function push(string $data, int $opcode = 1, int $flags = 1): bool
    {
        return $this->response->push($data,$opcode,$flags);
    }

    public function recv(float $timeout = 0): object|string|false
    {
        return $this->response->recv($timeout);
    }

    public function close(): bool
    {
        return $this->response->close();
    }

}
