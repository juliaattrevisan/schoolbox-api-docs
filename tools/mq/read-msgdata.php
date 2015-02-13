#!/usr/bin/php
<?php

if (count($argv) < 2) {
    die('Need one or more hash arguments, got ' . ($argc - 1) . "\n");
}

require_once __DIR__ . '/../../www/conf/conf.php';

for ($i = 1; $i < $argc; ++$i) {
    if (!preg_match('`^([0-9a-f]{40}):([0-9a-f]{40})$`', $argv[$i], $matches)) {
        echo "Invalid hash: $argv[$i]\n";
        continue;
    }

    $key = sprintf('msg:%s:m:%s', INSTANCE_ID, $matches[0]);

    $output[$matches[1]][$matches[2]] = json_decode(gzuncompress(`redis-cli get $key`), true);
}

if (empty($output)) {
    die("No data\n");
}

print_r($output);
exit;