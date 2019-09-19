<?php
include_once __DIR__ . "/../vendor/autoload.php";

const PKG_EOF = "\r\n\r\n";
const KEY = '#ivan_is_handsome_body#';

function getSign($data, $time) {
    $str = KEY . '#' . $time;
    foreach($data as $k => $v) {
        $v = is_array($v) ? json_encode($v) : $v;
        $str .= '#' . $k . '|' . $v;
    }
    return md5($str);
}


$data = [
    ['name' => 'ivan', 'age' => 26, 'height' => '62kg', 'high' => '167cm'],
    ['name' => 'siki', 'age' => 21, 'height' => '40kg', 'high' => '155cm'],
    ['name' => 'yuki', 'age' => 10, 'height' => '20kg', 'hight' => '132cm'],
    ['name' => 'ivan', 'age' => 26, 'height' => '62kg', 'high' => '167cm'],
    ['name' => 'siki', 'age' => 21, 'height' => '40kg', 'high' => '155cm'],
    ['name' => 'yuki', 'age' => 10, 'height' => '20kg', 'hight' => '132cm'],
    ['name' => 'ivan', 'age' => 26, 'height' => '62kg', 'high' => '167cm'],
    ['name' => 'siki', 'age' => 21, 'height' => '40kg', 'high' => '155cm'],
    ['name' => 'yuki', 'age' => 10, 'height' => '20kg', 'hight' => '132cm'],
    ['name' => 'ivan', 'age' => 26, 'height' => '62kg', 'high' => '167cm'],
    ['name' => 'siki', 'age' => 21, 'height' => '40kg', 'high' => '155cm'],
    ['name' => 'yuki', 'age' => 10, 'height' => '20kg', 'hight' => '132cm'],
    ['name' => 'ivan', 'age' => 26, 'height' => '62kg', 'high' => '167cm'],
    ['name' => 'siki', 'age' => 21, 'height' => '40kg', 'high' => '155cm'],
    ['name' => 'yuki', 'age' => 10, 'height' => '20kg', 'hight' => '132cm'],
    ['name' => 'ivan', 'age' => 26, 'height' => '62kg', 'high' => '167cm'],
    ['name' => 'siki', 'age' => 21, 'height' => '40kg', 'high' => '155cm'],
    ['name' => 'yuki', 'age' => 10, 'height' => '20kg', 'hight' => '132cm'],
];

$time = time();
$data['sign'] = getSign($data, $time);
$data['time'] = $time;
$req = [
    'cmd'  => 'receive',
    'data' => $data,
    'ext' => [],
];
$req = json_encode($req) . PKG_EOF;


$opt = getopt("c:n:s:f:p:");
$sync = new BenchMark\Sync($opt);
$sync->createSendData($req);
$sync->run();