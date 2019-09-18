<?php
class SwooleWebSocketServer {
    public function start ($opt) {
        if (count($opt) < 2) {
            exit("请填写地址和端口\n");
        }

        // 创建server对象，监听端口
        $server = new Swoole\WebSocket\Server($opt['h'], $opt['p']);

        $server->on('open', function (Swoole\WebSocket\Server $server, $request) {
            echo "server: handshake success with fd{$request->fd}\n";
        });
        
        $server->on('message', function (Swoole\WebSocket\Server $server, $frame) {
            echo "receive from {$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}\n";
            $server->push($frame->fd, "this is server");
        });
        
        $server->on('close', function ($ser, $fd) {
            echo "client {$fd} closed\n";
        });

        // 启动服务器
        $server->start();

    }
}

$opt = getopt("h:p:");
(new SwooleWebSocketServer())->start($opt);