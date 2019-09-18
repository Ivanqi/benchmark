<?php
namespace BenchMark;

use BenchMark\Service\PcntlBenchmark;

class Sync 
{
    protected $opt = []; 
    public function __construct($opt)
    {
        $this->optCheck($opt);
        $this->opt = $opt;
    }

    public function run()
    {
        $pb = new PcntlBenchmark(__CLASS__, trim($this->opt['f']));
        $pb->process_num = (int) $this->opt['c'];
        $pb->request_num = (int) $this->opt['n'];
        $pb->server_url = trim($this->opt['s']);
        $pb->server_config = parse_url($pb->server_url);
        $pb->send_data = $this->createSendData();
        $pb->read_len = 65536;
        if (!empty($this->opt['p'])) {
            $pb->show_detail = true;
        };
        // 请求数量最好的是进程数的倍数
        $pb->process_req_num = intval($pb->request_num / $pb->process_num);
        $pb->run();
        $pb->report();
        $pb->end();
    }

    // long_tcp
    public function long_tcp (PcntlBenchmark $pb) 
    {
        static $fp = null;
        static $i;
        $start = microtime(true);
        if (empty($fp)) {
            $fp = new \swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_SYNC);
            $end = microtime(true);
            $conn_use = $end - $start;
            $pb->max_conn_time = $conn_use;
            $i = 0;
            if (!$fp->connect($pb->server_config['host'], $pb->server_config['port'], 2)) {
                error:
                echo "Error:" . \swoole_strerror($fp->errCode) . "[{$fp->errCode}]\n";
                $fp = null;
                return false;
            }
            $start = $end;
        }
        // 写入socket
        if (!$fp->send($pb->send_data)) {
            goto error;
        }
        $end = microtime(true);
        $write_use = $end - $start;
        if ($write_use > $pb->max_write_time) {
            $pb->max_write_time = $write_use;
        }
        
        $start = $end;
        // 读取socket
        while(true) {
            $ret = $fp->recv(65530);
            if (empty($ret) or substr($ret, -1, 1) == "\n") {
                break;
            }
        }
        // var_dump($ret);
        $i++;
        if (empty($ret)) {
            echo $pb->pid, "#$i@", " is lost \n";
            return false;
        }
        $end = microtime(true);
        $read_use = $end - $start;
        if ($read_use > $pb->max_read_time)
        {
            $pb->max_read_time = $read_use;
        }
        return true;
    }

    private function optCheck($opt)
    {
        // 并发数量
        if(!isset($opt['c'])) {
            exit("require -c [process_num]. ep: -c 100\n");
        }
        if(!isset($opt['n'])) {
            exit("require -n [request_num]. ep: -n 10000\n");
        }
        if(!isset($opt['s'])) {
            exit("require -s [server_url]. ep: -s tcp://127.0.0.1:9999\n");
        }
        if(!isset($opt['f'])) {
            exit("require -f [test_function]. ep: -f short_tcp\n");
        }
    }

    private function createSendData()
    {
        $sendData = "GET / HTTP/1.1\r\n";
        $sendData .= "Host: www.baidu.com\r\n";
        $sendData .= "Connection: keep-alive\r\n";
        $sendData .= "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8\r\n";
        $sendData .= "User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/34.0.1847.116 Safari/537.36\r\n\r\n";
        return $sendData;
    }
}