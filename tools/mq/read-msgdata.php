#!/usr/bin/php
<?php

if (count($argv) < 2) {
    die('Need one or more hash arguments, got ' . ($argc - 1) . "\n");
}

require_once __DIR__ . '/../../www/conf/conf.php';

for ($i = 1; $i < $argc; ++$i) {
    if (!preg_match('`^[0-9a-f]{40}:[0-9a-f]{40}$`', $argv[$i], $matches)) {
        echo "Invalid hash: $argv[$i]\n";
        continue;
    }

    $key = sprintf('msg:%s:m:%s', INSTANCE_ID, $matches[0]);

    $data = gzuncompress(`redis-cli get $key`);

    echo " *** $argv[$i] *** \n";
    print_r(json_decode($data, true));

}
