#!/usr/bin/php
<?php
/**
 * Usage: /vagrant/tools/schema-check.php
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\IMapper;
use Schoolbox\Mapper;
use Schoolbox\Application\Daemon;

require_once __DIR__ . '/../conf/core/require.php';
$kernel = new Daemon\Kernel(
    __FILE__,
    [
        Daemon\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG
    ]
);

$log = $kernel->logger();
$log->info("Unmapped:");

$classes = array_map(
    function ($v)
    {
        return str_replace("/", "\\", substr($v, 0, -4));
    },
    allMapperClassFiles()
);

$output = [];

foreach ($classes as $classname) {

    try {
        $unmapped = dumpClassSpec($classname);

        if (count($unmapped) == 0) {
            continue;
        }

        $output[$classname] = [
            'unmapped' => $unmapped
        ];
        $log->info("    $classname:");
        foreach($unmapped as $field){
            $log->info("        $field");
        }

    } catch (\Exception $e) {
        // for information of what was excluded and why, uncomment this
        // $output[$classname] = [
        //     get_class($e) => $e->getMessage()
        // ];
    }
}


/**
 * @param string $dir
 * @param string $prefix
 * @return array
 */
function _listIn($dir, $prefix = '') {
    $dir = rtrim($dir, '\\/');
    $result = array();

    foreach (scandir($dir) as $f) {
        if ($f !== '.' and $f !== '..') {
            if (is_dir("$dir/$f")) {
                $result = array_merge($result, _listIn("$dir/$f", "$prefix$f/"));
            } else {
                $result[] = $prefix.$f;
            }
        }
    }

    return $result;
}


/**
 * @return array
 */
function allMapperClassFiles()
{
    return array_values(
        array_filter(
            _listIn(realpath(__DIR__ . '/../library')),
            function ($v)
            {
                return strpos($v, 'Schoolbox') === 0 && substr($v, -10) === 'Mapper.php';
            }
        )
    );
}


/**
 * @param string $classname
 * @return array
 */
function dumpClassSpec($classname)
{
    $rc = new ReflectionClass($classname);

    if (!$rc->implementsInterface(IMapper::class)) {
        throw new InvalidArgumentException("Class is not a Schoolbox Mapper");
    }

    if (!$rc->isInstantiable()) {
        throw new InvalidArgumentException("Mapper is not instantiable");
    }

    $constructor = $rc->getConstructor();

    // Don't attempt to construct if it's requires args
    if($constructor->getNumberOfRequiredParameters()){
        return [];
    }
    /** @var Mapper $mapper */
    $mapper = $rc->newInstance();

    return $mapper->listUnmappedFields();
}
