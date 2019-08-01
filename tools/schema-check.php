#!/usr/bin/php
<?php
/**
 * schema-check
 *
 * Finds schema columns that aren't known by their corresponding mappers (finds unmapped columns).
 * Originally used to identify deprecated columns.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */
declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\IMapper;use Schoolbox\Mapper;

require_once __DIR__ . '/../conf/core/require.php';

$app = new class extends CliTool\App
{
    // --help: print help
    private const OPT_LONG_HELP = 'help';


    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
        ];
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Finds schema columns that aren't known by their corresponding mappers (finds unmapped columns).
Originally used to identify deprecated columns.

Usage: schema-check.php


HELP;
            exit;
        }

        $this->getLogger()->info('Unmapped:');

        $classes = array_map(
            function ($v)
            {
                return str_replace('/', "\\", substr($v, 0, -4));
            },
            $this->allMapperClassFiles()
        );

        $output = [];

        foreach ($classes as $classname) {
            try {
                $unmapped = $this->dumpClassSpec($classname);

                if (\count($unmapped) === 0) {
                    continue;
                }

                $output[$classname] = [
                    'unmapped' => $unmapped
                ];

                $this->getLogger()->info("    {$classname}:");

                foreach($unmapped as $field){
                    $this->getLogger()->info("        {$field}");
                }
            } catch (\Exception $e) {
                // for information of what was excluded and why, uncomment this
                // $output[$classname] = [
                //     get_class($e) => $e->getMessage()
                // ];
            }
        }
    }

    /**
     * @param string $dir
     * @param string $prefix
     * @return array
     */
    private function listIn($dir, $prefix = ''): array {
        $dir = rtrim($dir, '\\/');
        $result = array();

        foreach (scandir($dir) as $f) {
            if ($f !== '.' and $f !== '..') {
                if (is_dir("$dir/$f")) {
                    $result = array_merge($result, $this->listIn("{$dir}/{$f}", "{$prefix}{$f}/"));
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
    private function allMapperClassFiles(): array
    {
        return array_values(
            array_filter(
                $this->listIn(dirname(__DIR__) . '/library'),
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
     * @throws InvalidArgumentException
     */
    private function dumpClassSpec($classname): array
    {
        $rc = new ReflectionClass($classname);

        if (!$rc->implementsInterface(IMapper::class)) {
            throw new InvalidArgumentException('Class is not a Schoolbox Mapper');
        }

        if (!$rc->isInstantiable()) {
            throw new InvalidArgumentException('Mapper is not instantiable');
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
};

$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
