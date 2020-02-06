<?php
namespace BenchMark\Service;

class PcntlBenchmark 
{
    public $test_func;
    public $class_name;
    public $process_num;
    public $request_num;
    public $process_req_num;
    public $server_url;
    public $server_config;
    public $send_data;
    public $read_len;

    public $time_end;
    public $main_pid;
    public $child_pid = [];

    public $show_detail = false;
    public $max_write_time = 0;
    public $max_read_time = 0;
    public $max_conn_time = 0;
    public $fp = NULL;

    public $pid;

    private $shm_key;
    protected $tmp_dir = '/tmp/swoole_bench/';

    public function __construct($className, $func) 
    {
        if (!method_exists($className, $func)) {
            exit(__CLASS__ . ": function[$func] not exits \n");
        }

        $this->class_name = $className;
        $this->tmp_dir = $this->returnTmpDir();
        if (!is_dir($this->tmp_dir)) {
            mkdir($this->tmp_dir);
        }
        $this->test_func = $func;
    }

    private function returnTmpDir()
    {
        return __DIR__ . '/../../tests/logs/';
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
        //posix_getpid 返回当前进程 id
        $this->main_pid = posix_getpid();
        $this->shm_key = $this->tmp_dir . 't.log';

        for ($i = 0; $i < $this->process_num; $i++) {
            $this->child_pid[] = $this->start([$this, 'worker']);
        }
        for ($i = 0; $i < $this->process_num; $i++) {
            $start = 0;
            // pcntl_wait 等待或返回fork的子进程状态
            $pid = pcntl_wait($status);
        }
        $this->time_end = microtime(true);
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
            $start = microtime(true);
        }

        $this->pid = posix_getpid();
        for ($i = 0; $i < $this->process_req_num; $i++) {
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