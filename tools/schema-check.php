#!/usr/bin/php
<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Mapper;

require_once __DIR__ . '/../conf/core/require.php';

/**
 * Finds schema columns that aren't known by their corresponding mappers (finds unmapped columns).
 * Originally used to identify deprecated columns.
 */
$app = new class extends CliTool\App {
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

//        $this->getLogger()->info('Unmapped:');

        $classes = array_map(
            static function ($v) {
                return str_replace('/', "\\", substr($v, 0, -4));
            },
            $this->allMapperClassFiles()
        );

        $output = [];

        foreach ($classes as $classname) {
            // Output the name: skip if not usable
            $this->getLogger()->info("{$classname}:");
            try {
                $mapper = $this->retrieveMapper($classname);
            } catch (InvalidArgumentException $e) {
                $this->getLogger()->notice("    {$e->getMessage()}");
                continue;
            } catch (\Throwable $e) {
                $this->getLogger()->warning("    {$e->getMessage()}");
                continue;
            }

            // Unmapped fields
            try {
                $unmapped = $mapper->listUnmappedFields();
                if (\count($unmapped) === 0) {
                    $this->getLogger()->info('    Unmapped fields: none');
                } else {
                    $this->getLogger()->info('    Unmapped fields:');
                    foreach ($unmapped as $field) {
                        $this->getLogger()->info("        {$field}");
                    }
                }
            } catch (\Throwable $e) {
                $this->getLogger()->warning("    Unmapped fields: error ({$e->getMessage()})");
            }


            // Query test: count
            try {
                $count = $mapper->countAll();
                if ($count > 0) {
                    $this->getLogger()->info('    Count test: pass');
                } else {
                    $this->getLogger()->notice('    Count test: skip (count = 0)');
                }
            } catch (\Throwable $e) {
                $this->getLogger()->warning("    Count test: error ({$e->getMessage()})");
            }


            // Query test: find
            try {
                $rows  = $mapper->findAll(['limit' => 1]);
                $count = count($rows);
                if ($count > 0) {
                    $this->getLogger()->info('    Find test: pass');
                } else {
                    $this->getLogger()->notice('    Find test: skip (count = 0)');
                }
            } catch (\Throwable $e) {
                $this->getLogger()->warning("    Find test: error ({$e->getMessage()})");
            }
        }
    }

    /**
     * @param string $dir
     * @param string $prefix
     * @return array
     */
    private function listIn($dir, $prefix = ''): array
    {
        $dir    = rtrim($dir, '\\/');
        $result = [];

        foreach (scandir($dir) as $f) {
            if ($f !== '.' and $f !== '..') {
                if (is_dir("$dir/$f")) {
                    $result = array_merge($result, $this->listIn("{$dir}/{$f}", "{$prefix}{$f}/"));
                } else {
                    $result[] = $prefix . $f;
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
                static function ($v) {
                    return strpos($v, 'Schoolbox') === 0 && substr($v, -10) === 'Mapper.php';
                }
            )
        );
    }


    /**
     * @param string $classname
     * @return Mapper
     * @throws InvalidArgumentException
     */
    private function retrieveMapper($classname): Mapper
    {
        $rc = new ReflectionClass($classname);

        if (!$rc->isSubclassOf(Mapper::class)) {
            throw new InvalidArgumentException('Class is not a Schoolbox Mapper');
        }

        if (!$rc->isInstantiable()) {
            throw new InvalidArgumentException('Mapper is not instantiable');
        }

        $constructor = $rc->getConstructor();

        // Don't attempt to construct if it's requires args
        if ($constructor->getNumberOfRequiredParameters() > 0) {
            throw new InvalidArgumentException('Mapper constructor requires params: not sure what they are');
        }

        return $rc->newInstance();
    }
};

$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
