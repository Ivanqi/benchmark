<?php
namespace BenchMark;

use BenchMark\Service\PcntlBenchmark;
use BenchMark\Service\WebSocketClient;
use BenchMark\Service\OptCheck;

class Sync 
{
    protected $opt = [];
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
        $pb->server_config = parse_url($pb->server_url);
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

    // short_tcp
    public function short_tcp(PcntlBenchmark $pb) 
    {
        $fp = new \swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_SYNC, 1);
        if (!$fp->connect($pb->server_config['host'], $pb->server_config['port'], 1)) {
            error:
            echo "Error: " . socket_strerror($fp->errCode). "[{$fp->errCode}]";
            return false;
        } else {
            if (!$fp->send($pb->send_data)) {
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

    // eof
    public function eof(PcntlBenchmark $pb) {
        static $client = null;
        static $i;
        $start = microtime(true);

        if (empty($client)) {
            $client = new \swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_SYNC);
            $client->set ([
                'open_eof_check' => true, 
                'package_eof' => "\r\n\r\n"
            ]);
            $end = microtime(true);
            $conn_use = $end - $start;
            $pb->max_conn_time = $conn_use;
            $i = 0;
            //echo "connect {$bc->server_url} \n";
            if (!$client->connect($pb->server_config['host'], $pb->server_config['port'], 2)) {
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
        if ($write_use > $pb->max_write_time) {
            $pb->max_write_time = $write_use;
        }

        $start = $end;
        // 读取socket
        $i++;
        $ret = $client->recv();
        if (empty($ret)) {
            echo $pb->pid , "#$i", "is lost \n";
            return false;
        } elseif (strlen($ret) != strlen($data)) {
            echo "#$i\tlength error \n";
            var_dump($ret);
            echo "-------------------------------\n";
            var_dump($data);
        }
        $end = microtime(true);
        $read_use = $end - $start;
        if ($read_use > $pb->max_read_time) {
            $pb->max_read_time = $read_use;
        }
        return true;
    }

    // long_tcp
    public function long_tcp(PcntlBenchmark $pb) 
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
        if ($read_use > $pb->max_read_time) {
            $pb->max_read_time = $read_use;
        }
        return true;
    }

    public function websocket(PcntlBenchmark $pb)
    {
        static $client = null;
        static $i;
        $start = microtime(true);

        if (empty($client)) {
            $client = new WebSocketClient($pb->server_config['host'], $pb->server_config['port']);
            if (!$client->connect()) {
                echo "connect failed \n";
                return false;
            }
            $end = microtime(true);
            $conn_use = $end - $start;
            $pb->max_conn_time = $conn_use;
            $i = 0;
            $start = $end;
        }

        // 写入socket
        if (!$client->send($pb->send_data)) {
            echo "send fail \n";
            return false;
        }
        $end = microtime(true);
        $write_use = $end - $start;
        if ($write_use > $pb->max_write_time) {
            $pb->max_write_time = $write_use;
        }

        $start = $end;
        // 读取socket
        $ret = $client->recv();
        // var_dump($ret);
        $i++;
        if (empty($ret)) {
            echo $pb->pid, "#$i@", " is lost \n";
            return false;
        }
        $end = microtime(true);
        $read_use = $end - $start;
        if ($read_use > $pb->max_read_time) {
            $pb->max_read_time = $read_use;
        }
        return true;
    }

    /**
     * 去除计时器的UDP
     * @param $bc
     * @return bool
     */
    public function udp(PcntlBenchmark $pb)
    {
        static $fp;
        if (empty($fp)) {
            $fp = stream_socket_client($pb->server_url, $errno, $errstr, 1);
            if (!$fp) {
                echo "{$errstr}[{$errno}]\n";
                return false;
            }
        }
        // 写入socket
        fwrite($fp, $pb->send_data);
        // 读取socket
        $ret = fread($fp, $pb->read_len);
        if (empty($ret)) {
            return false;
        }
        return true;
    }

    public function udp2(PcntlBenchmark $pb) 
    {
        static $fp;
        $start = microtime(true);
        if (empty($fp)) {
            $u = parse_url($pb->server_url);
            $fp = new \swoole_client(SWOOLE_SOCK_UDP);
            $fp->connect($u['host'], $u['port'], 0.5, 0);
            $end = microtime(true);
            $conn_use = $end - $start;
            $pb->max_conn_time = $conn_use;
            $start = $end;
        }
        // 写入socket
        $fp->send($pb->send_data);
        $end = microtime(true);
        $write_use = $end - $start;
        if ($write_use > $pb->max_write_time) {
            $pb->max_write_time = $write_use;
        }
        $start = $end;
        // 读取socket
        $ret = $fp->recv();
        if (empty($ret)) {
            return false;
        }
        $end = microtime(true);
        $read_use = $end - $start;
        if ($read_use > $pb->max_read_time) {
            $pb->max_read_time = $read_use;
        }
        return true;
    }

    public function long_socks5(PcntlBenchmark $pb) 
    {
        static $fp = null;
        static $i;
        $start = microtime(true);
        if (empty($fp)) {
            $fp = new swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_SYNC, 5);
            $end = microtime(true);
            $conn_use = $end - $start;
            $pb->max_conn_time = $conn_use;
            $i = 0;
            // echo "connect {$pb->server_url} \n"
            if (!$fp->connect($pb->server_config['host'], $pb->server_config['port'], 2)) {
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
        // var_dump($ret)
        $i++;
        if (empty($ret)) {
            echo $pb->pid, "#$i@", " is lost\n";
            return false;
        }
        $end = microtime(true);
        $read_use = $end - $start;
        if ($read_use > $pb->max_read_time) {
            $pb->max_read_time = $read_use;
        }
        return true;
    }

    public function createSendData($sendData)
    {
        $this->sendData = $sendData;
        // $sendData = "GET / HTTP/1.1\r\n";
        // $sendData .= "Host: www.baidu.com\r\n";
        // $sendData .= "Connection: keep-alive\r\n";
        // $sendData .= "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8\r\n";
        // $sendData .= "User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/34.0.1847.116 Safari/537.36\r\n\r\n";
        // return $sendData;
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