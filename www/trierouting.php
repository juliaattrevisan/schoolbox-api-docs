<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

set_time_limit(300);

use Alaress\Routing\Router;

$router = new Router('.');

echo "<h2>Add routes based on existing rabbit data</h2>";

echo '<div style="white-space:nowrap">';

$limit = 100000;

$fp = fopen('/vagrant/data/bind.list', 'r');


$a = memory_get_usage();

$x = microtime(true);
for ($i = 0; ($line = fgets($fp)) && $i < $limit; ++$i) {

    list ($exchange, $route) = explode("\t", trim($line));
    unset($line);

    $user = substr($exchange, strrpos($exchange, '.') + 1);
    unset($exchange);

    $router->add($route, $user, true);
    unset($route, $user);
}

$y = microtime(true);

$b = memory_get_usage();

printf(
    "<h1 id='result-%d'>%d routes loaded in %0.2fms at a cost of %0.2fMB</h1>\n",
    $limit,
    $i,
    ($y - $x) * 1000,
    ((($b - $a) / 1024) / 1024)
);

printf("<p>Average loadtime per route: <b>%0.2fms</b></p>\n", (($y - $x) * 1000) / $i);
printf("<p>Average memory per route: <b>%db</b></p>\n", ($b - $a) / $i);


fclose($fp);
unset($fp);
unset($limit);
unset ($limits);

echo "</div>\n";


$routes = [
    'INFO.notify.service.1.5657.1.duework.752.open',
    'INFO.notify.service.1.6043.1.duework.752.open',
    'INFO.notify.service.1.5921.1.duework.752.open',
    'INFO.notify.service.1.5817.1.duework.752.open',
    'INFO.notify.service.1.5738.1.duework.752.open',
];

foreach ($routes as $route) {
    echo "<hr/>\n<h2>Lookup $route</h2>\n";
    $n = microtime(true);
    try {
        $data = $router->get($route);
        $prefix = "<p>Found " . count($data) . " subscribers in";
    } catch (InvalidArgumentException $e) {
        $prefix = "<p style='color:red;'>Got IAE after";
        $data = [];
    }
    $m = microtime(true);

    printf(
        "%s %0.2fms</p>\n",
        $prefix,
        ($m - $n) * 1000
    );

    echo "<pre>", json_encode(array_keys($data)), "</pre>\n";
}

exit;

header('Content-Type: application/json');

die (json_encode($data));

