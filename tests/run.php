<?php
include_once __DIR__ . "/../vendor/autoload.php";
$opt = getopt("c:n:s:f:p:");
$sync = new BenchMark\Sync($opt);
$sync->run();