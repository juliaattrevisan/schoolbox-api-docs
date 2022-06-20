<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Test;

use Alaress_Byte_Format;
use Component_InstanceMapper;
use League\Flysystem\Adapter\NullAdapter;
use League\Flysystem\Filesystem;
use League\Flysystem\MountManager;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Comms\News\Audience;
use Schoolbox\Config\TestLoader;
use Schoolbox\External\SubjectMapper;
use Schoolbox\IMapper;
use Schoolbox\ISoftDeletable;
use Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan;
use Schoolbox\Resource;
use Schoolbox\TagCloud;
use Schoolbox\Video;
use SplFileInfo;

class ISoftDeletableTest extends TestCase
{

    /**
     * Ensure that if a mapper has a soft-delete column, then its model implements ISoftDeletable
     *
     * @param IMapper         $mapper
     * @param ReflectionClass $reflection
     * @param string          $className
     * @dataProvider providerMappers
     */
    public function testMapperAndModelConsistency(IMapper $mapper, ReflectionClass $reflection, string $className): void
    {
        $model              = $mapper->createModel();
        $reflectionMethod = $reflection->getMethod('isSoftDeletable');
        $isModelSoftDeletable       = $model instanceof ISoftDeletable;
        $isMapperSoftDeleteColumn   = (bool)$reflectionMethod->invoke($mapper);
        $message               = "'{$className}' model is" . ($isModelSoftDeletable ? '' : ' not') .
            ' soft-deletable but mapper is ' . ($isMapperSoftDeleteColumn ? '' : 'not ') . 'soft-deletable';
        $this->assertEquals($isModelSoftDeletable, $isMapperSoftDeleteColumn, $message);
    }


    /**
     * Provides all mappers to be tested
     *
     * @return array
     */
    public function providerMappers(): array
    {
        $this->loadAllMapperClasses();
        $mappers = [];

        foreach (get_declared_classes() as $className) {
            // Is the class not a mapper?
            if (!$this->isValidMapperClass($className)) {
                continue;
            }
            $reflection = new ReflectionClass($className);
            if ($reflection->isAbstract()) {
                // Test concrete descendants which will implicitly test the abstract
                continue;
            }
            if (!$reflection->hasMethod('getInstance')) {
                // Does not have a getInstance, probably not a real mapper
                continue;
            }
            /**
             * @var \Schoolbox\Mapper $mapper
             */
            // use 'new' instead of 'getInstance()' to avoid the postSetup() hook
            $mapper = new $className();

            if (!$mapper->getModel()) {
                // Model is undefined, no need to check
                continue;
            }
            $mapper->setConfigLoader(new TestLoader());
            $mappers[] = [$mapper, $reflection, $className];
        }
        return $mappers;
    }


    /**
     * Returns whether the specified class name is a valid mapper class
     *
     * @param string $className
     * @return bool
     */
    private function isValidMapperClass(string $className): bool
    {
        // Because they're not real mappers
        $exclusions = [
            SubjectMapper::class            => true,
            TagCloud\Mapper::class          => true,
            RubricDataSource::class         => true,
            Audience\Mapper::class          => true,

            // Should be tested, but requires out-of-scope change, see SA-4936
            Component_InstanceMapper::class => true,
            Plagscan\Mapper::class          => true,
            Video\QueueMapper::class        => true,
            Video\ResourceVideo::class      => true,
        ];

        if (!preg_match('/Mapper$/', $className)) {
            return false;
        }
        if (!class_exists($className)) {
            // Not a class (may be an interface)
            return false;
        }
        if (array_key_exists($className, $exclusions)) {
            // In exclusion list, usually not a real mapper
            return false;
        }
        return true;
    }


    /**
     * @param string $className The name of the relevant class
     * @return string The path to the file containing the class or false
     */
    private function getClassFile($className): string
    {
        $classSegments = preg_split('/[_\\\\]/', $className);
        return __DIR__ . '/../../../library/' . implode('/', $classSegments) . '.php';
    }


    /**
     * Load all mapper classes using require_once to avoid including twice
     */
    private function loadAllMapperClasses(): void
    {
        // Attempt to load and therefore parse every single PHP class in /library
        foreach (
            new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(__DIR__ . '/../../../library/', RecursiveDirectoryIterator::SKIP_DOTS)
            ) as $item
        ) {
            /** @var SplFileInfo $item */
            if (!$item->isFile()) {
                continue;
            }
            if (preg_match('[^/usr/share/schoolbox/library/(.*Mapper)\.php$]', $item->getRealPath(), $matches)) {
                $classBase = $matches[1];
                // Candidate class names
                $candidates = [
                    str_replace('/', '\\', $classBase),
                    str_replace('/', '_', $classBase),
                ];

                foreach ($candidates as $candidate) {
                    try {
                        /** @noinspection PhpIncludeInspection */
                        require_once $this->getClassFile($candidate);
                    } catch (\Throwable $e) {
                        break;
                    }
                }
            }
        }
    }
}
