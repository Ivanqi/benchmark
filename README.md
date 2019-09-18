#### TCP/UDP压测工具
- 工具来源：[链接🔗](https://github.com/swoole/swoole-src/tree/master/benchmark)

#### 同步压测工具-使用方式
- 说明
  - Swoole提供了一套TCP/UDP压测工具
  - 基于swoole_client + pcntl实现
  - 与ab，http_bench等工具不同，是基于多进程实现并发测试的
- 例子
```
php tests/run.php  -c 100 -n 10000 -s tcp://127.0.0.1:9501 -f long_tcp
```
- 参数说明
  - -c 参数，并发的数量，会启动对应数量的进程用于测试
  - -n 参数，请求的总数量，-n 10000, -c 100，平均到每个子进程的数量为100
  - -s 参数，Server的IP:PORT
  - -f 参数，测试单元的名称，目前提供了long_tcp/short_tcp/udp/websocket 函数，可以自行实现单元测试函数
- 测试结果
```
concurrency:    100 //并发数量
request num:    10000 //请求总数
lost num:   0 //失败次数
success num:    10000 //成功次数
total time: 0.157 //总耗时
req per second: 63558 //qps，每秒处理的请求数
one req use(ms):    0.015  //单个请求的平均时长，此结果目前不准确，请勿作为参考
```

#### 异步压测工具
- 例子
```
php test/run_async.php -c 100 -n 10000 -s tcp://127.0.0.1:9501 -f long_tcp
```
