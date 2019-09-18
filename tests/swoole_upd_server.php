<?php
class SwooleUdpServer {
    public function start ($opt) {
        if (count($opt) < 2) {
            exit("请填写地址和端口\n");
        }

        // 创建server对象，监听端口
        $serv = new \swoole_server($opt['h'], $opt['p'], SWOOLE_BASE, SWOOLE_SOCK_UDP);

        $serv->on('Packet', function ($serv, $data, $clientInfo) {
            //发送给客户端 用sendto
            print_r(['data', $data]);
            $serv->sendto($clientInfo['address'], $clientInfo['port'], "Server " .$data);
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
(new SwooleUdpServer())->start($opt);