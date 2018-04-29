<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
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
     * @throws \PHPUnit\Framework\Exception
     */
    public function testErrorIdUniqueness(): void
    {
        $reflection = new ReflectionClass(Error\Mapper::class);
        $constants  = $reflection->getConstants();

        $uniqueIds = [];
        foreach ($constants as $name => $idValue) {
            if ($this->isErrorIdConst($name)) {
                $this->assertArrayNotHasKey($idValue, $uniqueIds);
                $uniqueIds[$idValue] = true;
            }
        }
    }


    /**
     * Returns whether the specified constant is one of our error id constants
     *
     * @param string $constantName
     * @return bool
     */
    private function isErrorIdConst(string $constantName): bool
    {
        return 0 === strpos($constantName, 'ERROR_');
    }
}
