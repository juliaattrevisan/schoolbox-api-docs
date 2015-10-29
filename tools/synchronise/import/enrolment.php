#!/usr/bin/php
<?php

require_once(__DIR__ . '/../../../include/Bootstrap.php');

$parser = new \Schoolbox\External\Importer\Enrolment();
$reader = new \Alaress\Csv\Reader(array_pop($argv));
$reader->setLoadFromFirst(true)->setFillDown(true);

// The logger
$logger = new \Alaress\Lumberjack\Logger();
$parser->save($reader, $logger);
