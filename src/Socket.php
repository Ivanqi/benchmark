<?php
namespace BenchMark;

use BenchMark\Service\OptCheck;
use BenchMark\Service\PcntlBenchmark;
use BenchMark\Service\StreamClient;

class Socket {

    protected $opt = [];
    // const SOCKET_TCP = 'tcp';
    // const SOCKET_UDP = 'udp';
    // const SOCKET_UNIX = 'unix';
    private $sendData = '';

    public function __construct($opt)
    {
        OptCheck::check($opt);
        $this->opt = $opt;
    }

    public function run()
    {
        $pb = new PcntlBenchmark(__CLASS__, trim($this->opt['f']));
        $pb->process_num = (int) $this->opt['c'];
        $pb->request_num = (int) $this->opt['n'];
        $pb->server_url = trim($this->opt['s']);
        // $pb->server_config = parse_url($pb->server_url);
        $pb->send_data = $this->sendData;
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


    public function long_tcp(PcntlBenchmark $pb) 
    {
        static $fp = null;
        static $i;
        $start = microtime(true);
        if (empty($fp)) {
            $fp = new StreamClient();
            $end = microtime(true);
            $conn_use = $end - $start;
            $pb->max_conn_time = $conn_use;
            $i = 0;
            if (!$fp->connect($pb->server_url)) {
                echo "连接失败 \n";
                return false;
            }
        }
        $ret = $fp->send($pb->send_data, function() use ($start, &$end, &$write_use, &$pb){
            $end = microtime(true);
            $write_use = $end - $start;
            if ($write_use > $pb->max_write_time) {
                $pb->max_write_time = $write_use;
            }
        }, function() use (&$start, $end) {
            $start = $end;
        });
        $i++;
        if (!$ret) {
            echo $pb->pid, "#$i@", " is lost \n";
            echo "发送数据失败\n";
            return false;
        }

        $end = microtime(true);
        $read_use = $end - $start;
        if ($read_use > $pb->max_read_time) {
            $pb->max_read_time = $read_use;
        }
        return true;
    }

    // public function short_tcp(PcntlBenchmark $pb) 
    // {

    // }

    public function setSentData($sendData)
    {
        $this->sendData = $sendData;
        
    }
}