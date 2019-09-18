<?php
include_once __DIR__ . "/../vendor/autoload.php";
$opt = getopt("c:n:s:f:p:l:");
$async = new BenchMark\Async($opt);
$async->run();