<?php
namespace BenchMark\Service;

class StreamClient 
{
    private $errno = 0;
    private $errmsg = '';
    public $keepAlive = true;

    // 1024 * 8 缓存区 = 8kb
    public $readBuffer = 8192;
    public $writeBuffer = 8192;
    private $url;
    private $clientStream;
    private static $maxConnectSeconds = 2;
    
    public function connect($url)
    {
        $this->url = $url;
        return $this->createClientStream();
    }

    private function createClientStream()
    {
        $schema = parse_url($this->url, PHP_URL_SCHEME);
        if ($schema == 'unix') {
            $uri = 'unix://' . parse_url($this->url, PHP_URL_PATH);
        }
        // 创建上下文
        $context = @stream_context_create();
        // 用 $url 和上下文创建服务器字节流
        $clientStream = @stream_socket_client($this->url, $this->errno, $this->errmsg, 3600, STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT, $context);
        if ($clientStream === false) {
           return false;
        }

        // stream_set_blocking 将一个数据流设置为堵塞或者非堵塞状态
        @stream_set_blocking($clientStream, false);
        // stream_set_read_buffer 设置流读文件缓冲区
        @stream_set_read_buffer($clientStream, $this->readBuffer);
        // stream_set_write_buffer 设置流写文件缓冲区
        @stream_set_write_buffer($clientStream, $this->writeBuffer);

        if (in_array($schema, ['tcp', 'unix'])) {
            // 用客户端字节流创建socket
            $socket = @socket_import_stream($clientStream);

            // 设置配置项作用范围，第二个参数，对socket有效
            @socket_set_option($socket, SOL_SOCKET, SO_KEEPALIVE, (int)$this->keepAlive);

            if ($schema === 'tcp') {
                // 设置配置项作用范围，第二个参数，针对TCP有效
                @socket_set_option($socket, SOL_TCP, TCP_NODELAY, (int)$this->noDelay);
            }
        }
        $this->clientStream = $clientStream;
        return $this;
    }

    public function send($request, $callback1 = false, $callback2 = false) 
    {
        if (!$callback1) {
            $callback1();
        }
        // stream_socket_sendto 向Socket发送数据，不管其连接与否
        @stream_socket_sendto($this->clientStream, $request);
        if (!$callback2) {
            $callback2();
        }
        $start = time();
        while (true) {
            // stream_socket_recvfrom 从连接或未连接的套接字接收数据
            $data = @stream_socket_recvfrom($this->clientStream, 1024 * 8);
            if (empty($data) or substr($data, -1, 1) == "\n") {
                break;
            }
        }
        fclose($this->clientStream);
        return $data;
    }
}