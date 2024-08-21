<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Error\Test;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Schoolbox\Error;

class UniqueErrorConstTest extends TestCase
{

    /**
     * Ensure that all errorId constant values are unique
     *
     * @param array  $errorConstants
     * @param string $errorName
     * @param int    $errorValue
     * @throws \PHPUnit\Framework\AssertionFailedError
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerErrorConstants')]
    public function testErrorIdUniqueness(array $errorConstants, string $errorName, int $errorValue): void
    {
        $collisions = [];
        foreach ($errorConstants as $name => $value) {
            if ($errorValue === $value) {
                $collisions[] = $name;
            }
        }

        if (\count($collisions) >= 2) {
            $message = "Non-unique constant found. const {$errorName} = {$errorValue}";
            $this->fail($message);
        } else {
            $this->addToAssertionCount(1);
        }
    }


    /**
     * @return array
     * @throws \ReflectionException
     */
    public static function providerErrorConstants(): array
    {
        $data           = [];
        $errorConstants = self::getErrorConstants();

        foreach ($errorConstants as $name => $idValue) {
            $data[] = [$errorConstants, $name, $idValue];
        }
        return $data;
    }


    /**
     * Returns an array of ErrorConstant name => value
     *
     * @return int[]
     * @throws \ReflectionException
     */
    private static function getErrorConstants(): array
    {
        $errorConstants = [];
        $reflection     = new ReflectionClass(Error\Mapper::class);

        foreach ($reflection->getConstants() as $name => $idValue) {
            // Ignore irrelevant constants
            if (self::isErrorIdConst($name)) {
                $errorConstants[$name] = $idValue;
            }
        }

        return $errorConstants;
    }


    /**
     * Returns whether the specified constant is one of our error id constants
     *
     * @param string $constantName
     * @return bool
     */
    private static function isErrorIdConst(string $constantName): bool
    {
        return 0 === strpos($constantName, 'ERROR_');
    }
}
