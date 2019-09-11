<?php
include_once "./websocket/WebSocketClient.php";
class Swoole_Benchmark 
{
    public $test_func;
    public $class_name;
    public $process_num;
    public $request_num;
    public $server_url;
    public $server_config;
    public $send_data;
    public $read_len;

    public $time_end;
    private $shm_key;
    public $main_pid;
    public $child_pid = [];

    public $show_detail = false;
    public $max_write_time = 0;
    public $max_read_time = 0;
    public $max_conn_time = 0;

    public $pid;

    protected $tmp_dir = '/data/tmp/swoole_bench/';

    public function __construct($className, $func) 
    {
        if (!method_exists($className, $func)) {
            exit(__CLASS__ . ": function[$func] not exits \n");
        }
        $this->class_name = $className;
        if (!is_dir($this->tmp_dir)) {
            mkdir($this->tmp_dir);
        }
        $this->test_func = $func;
    }

    public function end() 
    {
        unlink($this->shm_key);
        foreach ($this->child_pid as $pid) {
            $f = $this->tmp_dir . 'lost_' . $pid . '.log';
            if (is_file($f)) {
                unlink($f);
            }
        }
    }

    public function run()
    {
        $this->main_pid = posix_getpid();
        $this->shm_key = $this->tmp_dir . 't.log';

        for ($i = 0; $i < $this->process_num; $i++) {
            $this->child_pid[] = $this->start([$this, 'worker']);
        }
        for ($i = 0; $i < $this->process_num; $i++) {
            $start = 0;
            $pid = pcntl_wait($status);
        }
        $this->time_end = microtime();
    }

    public function init_signal()
    {
        pcntl_signal(SIGUSR1, [$this, 'sig_handle']);
    }

    public function sig_handle($sig) 
    {
        switch($sig) {
            case SIGUSR1:
                return ;
        }
        $this->init_signal();
    }

    public function start($func) 
    {
        $pid = pcntl_fork();
        if ($pid > 0) {
            return $pid;
        } else if ($pid == 0) {
            $this->worker();
        } else {
            echo "Error: fork fail \n";
        }
    }

    public function worker() 
    {
        $lost = 0;
        if (!file_exists($this->shm_key)) {
            file_put_contents($this->shm_key, microtime(true));
        }
        if ($this->show_detail) {
            $start = microtime();
        }

        $this->pid = posix_getpid();

        for ($i = 0; $i < $this->process_num; $i++) {
            $func = $this->test_func;
            if (method_exists($this->class_name, $this->test_func)) {
                if (call_user_func([$this->class_name, $this->test_func], $this)) {
                    $lost++;
                }
            }
        }
        $file_path = $this->tmp_dir . 'lost_' . $this->pid . '.log';
        if ($this->show_detail) {
            $log = $this->pid . "#\ttotal_use(s): " . substr(microtime(true) - $start, 0, 5);
            $log .= "\tconnect(ms): " . substr($this->max_conn_time * 1000, 0, 5);
            $log .= "\twrite(ms): " . substr($this->max_write_time * 1000, 0, 5);
            $log .= "\tread(ms): " . substr($this->max_read_time * 1000, 0, 5);
            echo $this->tmp_dir . 'lost_' . $this->pid . ".log\n";
            echo $lost . "\n" . $log;
            $content = $lost . "\n" . $log;
            file_put_contents($file_path, $content);
        } else {
            file_put_contents($file_path, $lost);
        }
        exit(0);
    }

    public function report()
    {
        $time_start = file_get_contents($this->shm_key);
        $usetime = $this->time_end - $time_start;
        $lost = 0;

        foreach ($this->child_pid as $f) {
            $file = $this->tmp_dir . 'lost_' . $f . '.log';
            if (is_file($file)) {
                $_lost = file_get_contents($file);
                $log = explode("\n", $_lost, 2);
            }
            if (!empty($log)) {
                $lost += intval($log[0]);
                if ($this->show_detail) {
                    echo $log[1], "\n";
                }
            }
        }

        // 并发量
        echo "concurrency: \t" . $this->process_num , "\n";
        // 请求量
        echo "request num: \t" . $this->request_num , "\n";
        // 请求量
        echo "lost num: \t" . $lost , "\n";
        // 请求量
        echo "success num: \t" . ($this->request_num - $lost) , "\n";
        // 总时间
        echo "total time: \t" . substr($usetime, 0, 5) , "\n";
        // 每秒处理能力
        echo "req pre second: \t" . intval($this->request_num / $usetime) , "\n";
        // 每次请求平均时间
        echo "one req use(ms): \t" . substr($usetime / $this->request_num * 1000, 0, 5) , "\n\n\n";
    }
}


class BenchMark{
    protected $opt = [];
    public function __construct($opt) 
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
        $this->opt = $opt;
        $this->init_benchmark();
    }

    private function init_benchmark()
    {
        $bc = new Swoole_Benchmark(__CLASS__, trim($this->opt['f']));
        $bc->process_num = (int) $this->opt['c'];
        $bc->request_num = (int) $this->opt['n'];
        $bc->server_url = trim($this->opt['s']);
        $bc->server_config = parse_url($bc->server_url);
        $bc->send_data = "GET / HTTP/1.1\r\n";
        $bc->send_data .= "Host: www.baidu.com\r\n";
        $bc->send_data .= "Connection: keep-alive\r\n";
        $bc->send_data .= "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8\r\n";
        $bc->send_data .= "User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/34.0.1847.116 Safari/537.36\r\n\r\n";
        $bc->read_len = 65536;
        if(!empty($this->opt['p'])) {
            $bc->show_detail = true;
        };
        // 请求数量最好的是进程数的倍数
        $bc->process_req_num = intval($bc->request_num / $bc->process_num);
        $bc->run();
        $bc->report();
        $bc->end();
    }

    public function eof(Swoole_Benchmark $bc) {
        static $client = null;
        static $i;
        $start = microtime();
        if (empty($client)) {
            $client = new \swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_SYNC);
            $client->set ([
                'open_eof_check' => true, 
                'package_eof' => "\r\n\r\n"
            ]);
            $end = microtime();
            $conn_use = $end - $start;
            $bc->max_conn_time = $conn_use;
            $i = 0;
            //echo "connect {$bc->server_url} \n";
            if (!$client->connect($bc->server_config['host'], $bc->server_config['port'], 2)) {
                error:
                echo "Error: " . \swoole_strerror($client->errCode) . "[{$client->errCode}]\n";
                $client = null;
                return false;
            }
            $start = $end;
        }

        // 写入socket
        $data = str_repeat('A', rand(100, 200)) . "\r\n\r\n";
        if (!$client->send($data)) {
            goto error;
        }

        $end = microtime(true);
        $write_use = $end - $start;
        if ($write_use > $bc->max_write_time) {
            $bc->max_write_time = $write_use;
        }

        $start = $end;
        // 读取socket
        $i++;
        $ret = $client->recv();
        if (empty($ret)) {
            echo $bc->pid , "#$i", "is lost \n";
            return false;
        } elseif (strlen($ret) != strlen($data)) {
            echo "#$i\tlength error \n";
            var_dump($ret);
            echo "-------------------------------\n";
            var_dump($data);
        }
        $end = microtime(true);
        $read_use = $end - $start;
        if ($read_use > $bc->max_read_time) {
            $bc->max_read_time = $read_use;
        }
        return true;
    }

    public function long_tcp (Swoole_Benchmark $bc) 
    {
        static $fp = null;
        static $i;
        $start = microtime();
        if (empty($fp)) {
            $fp = new \swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_SYNC);
            $end = microtime(true);
            $conn_use = $end - $start;
            $bc->max_conn_time = $conn_use;
            $i = 0;
            //echo "connect {$bc->server_url} \n";
            if (!$fp->connect($bc->server_config['host'], $bc->server_config['port'], 2)) {
                error:
                echo "Error:" . \swoole_strerror($fp->errCode) . "[{$fp->errCode}]\n";
                $fp = null;
                return false;
            }
            $start = $end;
        }

        // 写入socket
        if (!$fp->send($bc->send_data)) {
            goto error;
        }
        $end = microtime(true);
        $write_use = $end - $start;
        if ($write_use > $bc->max_write_time) {
            $bc->max_write_time = $write_use;
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
            echo $bc->pid, "#$i@", " is lost \n";
            return false;
        }
        $end = microtime(true);
        $read_use = $end - $start;
        if ($read_use > $bc->max_read_time)
        {
            $bc->max_read_time = $read_use;
        }
        return true;
    }

    public function websocket(Swoole_Benchmark $bc)
    {
        static $client = null;
        static $i;
        $start = microtime(true);

        if (empty($client)) {
            $client = new WebSocketClient($bc->server_config['host'], $bc->server_config['port']);
            if (!$client->connect()) {
                echo "connect failed \n";
                return false;
            }
            $end = microtime(true);
            $conn_use = $end - $start;
            $bc->max_conn_time = $conn_use;
            $i = 0;
            $start = $end;
        }

        // 写入socket
        if (!$client->send($bc->send_data)) {
            echo "send fail \n";
            return false;
        }
        $end = microtime(true);
        $write_use = $end - $start;
        if ($write_use > $bc->max_write_time) {
            $bc->max_write_time = $write_use;
        }

        $start = $end;
        // 读取socket
        $ret = $client->recv();
        // var_dump($ret);
        $i++;
        if (empty($ret)) {
            echo $bc->pid, "#$i@", " is lost \n";
            return false;
        }
        $end = microtime(true);
        $read_use = $end - $start;
        if ($read_use > $bc->max_read_time) {
            $bc->max_read_time = $read_use;
        }
        return true;
    }

    /**
     * 去除计时器的UDP
     * @param $bc
     * @return bool
     */
    public function udp(Swoole_Benchmark $bc)
    {
        static $fp;
        if (empty($fp)) {
            $fp = stream_socket_client($bc->server_url, $errno, $errstr, 1);
            if (!$fp) {
                echo "{$errstr}[{$errno}]\n";
                return false;
            }
        }
        // 写入socket
        fwrite($fp, $bc->send_data);
        // 读取socket
        $ret = fread($fp, $bc->read_len);
        if (empty($ret)) {
            return false;
        }
        return true;
    }

    public function upd2(Swoole_Benchmark $bc) 
    {
        static $fp;
        $start = microtime(true);
        if (empty($fp)) {
            $u = parse_url($bc->server_url);
            $fp = new \swoole_client(SWOOLE_SOCK_UDP);
            $fp->connect($u['host'], $u['port'], 0.5, 0);
            $end = microtime(true);
            $conn_use = $end - $start;
            $bc->max_conn_time = $conn_use;
            $start = $end;
        }
        // 写入socket
        $fp->send($bc->send_data);
        $end = microtime(true);
        $write_use = $end - $start;
        if ($write_use > $bc->max_write_time) {
            $bc->max_write_time = $write_use;
        }
        $start = $end;
        // 读取socket
        $ret = $fp->recv();
        if (empty($ret)) {
            return false;
        }
        $end = microtime(true);
        $read_use = $end - $start;
        if ($read_use > $bc->max_read_time) {
            $bc->max_read_time = $read_use;
        }
        return true;
    }

    public function short_tcp($bc) 
    {
        $fp = new swoole_client(SWOOLE_SOKC_TCP, SWOOLE_SOCK_SYNC);
        if (!$fp->connect($bc->server_config['host'], $bc->server_config['port'], 1)) {
            error:
            echo "Error: " . socket_strerror($fp->errCode). "[{$fp->errCode}]";
            return false;
        } else {
            if (!$fp->send($bc->send_data)) {
                goto error;
            }
            $ret = $fp->recv();
            $fp->close();
            if (!empty($ret)) {
                return true;
            } else {
                return false;
            }
        }
    }

    public function long_socks5($bc) {
        static $fp = null;
        static $i;
        $start = microtime(true);
        if (empty($fp)) {
            $fp = new swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_SYNC, 5);
            $end = microtime(true);
            $conn_use = $end - $start;
            $bc->max_conn_time = $conn_use;
            $i = 0;
            // echo "connect {$bc->server_url} \n"
            if (!$fp->connect($bc->server_config['host'], $bc->server_config['port'], 2)) {
                error:
                echo "Error: " . swoole_strerror($fp->errCode) . "[{$fp->errCode}] \n";
                $fp = null;
                return false;
            }
            $fp->send(pack("C3", 0x05, 0x01, 0x00)); // greet
            $data = $fp->recv();
            $response = unpack("Cversion/Cmethod", $data);
            if ($response['version'] != 0x05) {
                exit("SOCKS version is no supoorted.");
            }
            $headers = $this->getHeader($response);
            if (empty($headers['port'])) {
                $headers['port'] = 80;
            }
            $g = pack("C5", 0x05, 0x01, 0x00, 0x03, strlen($headers['host'])) . $headers['hosts'] . pack("n", $headers['port']);
            $fp->send($g);
            $data = $fp->recv();
            $response = unpack("Cversion/Cresult/Creg/Ctype/Lip/Sport", $data);
            if ($response['result'] != 0x00) {
                echo "SOCKS connection request failed:" . $this->getSocketRefusalMsg($response['result']) , $response['result'];exit;
            }
            $start = $end;
        }
        // 写入socket 
        if (!$fp->send($bc->send_data)) {
            goto error;
        }
        $end = microtime(true);
        $write_use = $end - $start;
        if ($write_use > $bc->max_write_time) {
            $bc->max_write_time = $write_use;
        }
        $start = $end;
        // 读取socket
        while(true) {
            $ret = $fp->recv(65530);
            if (empty($ret) or substr($ret, -1, 1) == "\n") {
                break;
            }
        }
        // var_dump($ret)
        $i++;
        if (empty($ret)) {
            echo $bc->pid, "#$i@", " is lost\n";
            return false;
        }
        $end = microtime(true);
        $read_use = $end - $start;
        if ($read_use > $bc->max_read_time) {
            $bc->max_read_time = $read_use;
        }
        return true;
    }

    public function getSocketRefusalMsg($msg)
    {
        return $msg;
    }

    public function getHeader($message) 
    {
        // 标准每行应该以"\r\n"行终止，这里兼容以"\n"作为终止的情况，所以按"\n"分割行
        $lines = explode("\n", $message);
        foreach($lines as &$line) {
            // 按 "\n"分割行以后，某些行末可能存在"\r"字符，这里将其过滤
            $line = rtrim($line, "\r");
        }
        unset($line);
        if (count($lines) <= 0) {
            return false;
        }
        $headers = [];
        foreach ($lines as $line) {
            $pos = strpos($line, ':');
            // 非标准首部,抛弃
            if ($pos === false) {
                continue;
            }
            $field = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));

            // 如果有host头部，重新设置host和port
            if (strtolower($field) === 'host') {
                $segments = explode(':', $value);
                $host = $segments[0];
                $headers['host'] = $host;
                if (isset($segments[1])) {
                    $port = intval($segments[1]);
                    $headers['port'] = $port;
                }
            }
            $headers[$field] = $value;
        }
        return $headers;
    }
}

$opt = getopt("c:n:s:f:p:");
new BenchMark($opt);