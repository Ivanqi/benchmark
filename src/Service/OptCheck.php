<?php
namespace BenchMark\Service;
class OptCheck 
{
    public static function check($opt)
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
}