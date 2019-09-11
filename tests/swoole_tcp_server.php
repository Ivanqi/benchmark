<?php
class SwooleTcpServer {
    public function start ($opt) {
        if (count($opt) < 2) {
            exit("请填写地址和端口\n");
        }

        // 创建server对象，监听端口
        $serv = new \swoole_server($opt['h'], $opt['p']);

        // 监听连接进入事件
        $serv->on('connect', function ($serv, $fd) {
            echo "Client: Connect.\n";
        });

        // 监听数据接收事件
        $serv->on('receive', function($serv, $fd, $from_id, $data) {
            $serv->send($fd, "Server:" . $data);
        });

        // 监听连接关闭事件
        $serv->on('close', function ($serv, $fd) {
            echo "Client: Close.\n";
        });

        // 启动服务器
        $serv->start();

    }
}

$opt = getopt("h:p:");
(new SwooleTcpServer())->start($opt);