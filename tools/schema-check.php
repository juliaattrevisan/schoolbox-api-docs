<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Schoolbox\IMapper;
use Schoolbox\Mapper;

if (isset($this)) {

    $pathInfo = '';

    /** @var SitePage $this */
    if (count($this->arg)) {
        $pathInfo = '/' . implode('/', $this->arg);
    }

    $this->localRedir("/schema-check.php?path=$pathInfo");
    exit;
}

require_once __DIR__ . '/../include/Bootstrap.php';


if (isset($_GET['path']) && strlen(trim($_GET['path']))) {
    $path = str_replace("/", "\\", $_GET['path']);

    $classname = "Schoolbox$path";

    if (!class_exists($classname)) {
        die("Class doesn't exist");
    }

    $classes = [ $classname ];

} else {

    $classes = array_map(
        function ($v)
        {
            return str_replace("/", "\\", substr($v, 0, -4));
        },
        allMapperClassFiles()
    );

}

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

    } catch (\Exception $e) {
        // for information of what was excluded and why, uncomment this
        // $output[$classname] = [
        //     get_class($e) => $e->getMessage()
        // ];
    }
}

header("Content-Type: application/json");
die(json_encode($output));


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

    /** @var Mapper $mapper */
    $mapper = $rc->newInstance();

    return $mapper->listUnmappedFields();
}
