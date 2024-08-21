<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Folder\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Resource\Folder;

/**
 * @codeCoverageIgnore
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\Resource\Folder\Model::class)]
class RolledOverFolderCodeTest extends TestCase
{
    /**
     * Tests a folder name without rolled over codes
     */
    public function testNone(): void
    {
        $folder = (new Folder\Model())
            ->setName("A folder name");

        $this->assertEquals([], $folder->getRolledOverCodes());
    }


    /**
     * Tests a folder name with a single rolled over code
     *
     * @param string   $folderName
     * @param string[] $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerSingle')]
    public function testSingle(string $folderName, array $expected): void
    {
        $folder = (new Folder\Model())
            ->setName($folderName);

        $this->assertEquals($expected, $folder->getRolledOverCodes());
    }


    /**
     * Tests a folder name with multiple rolled over codes
     *
     * @param string   $folderName
     * @param string[] $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerMultiple')]
    public function testMultiple(string $folderName, array $expected): void
    {
        $folder = (new Folder\Model())
            ->setName($folderName);

        $this->assertEquals($expected, $folder->getRolledOverCodes());
    }


    public static function providerSingle(): array
    {
        return [
            ['A folder [ABC]', ['ABC']],
            ['A folder [ ABC]', ['ABC']],
            ['A folder [ABC ]', ['ABC']],
            ['A folder [ ABC ]', ['ABC']],
            ['A folder [EN/ABC]', ['EN/ABC']],
            ['A folder [ EN/ABC]', ['EN/ABC']],
            ['A folder [EN/ABC ]', ['EN/ABC']],
            ['A folder [ EN/ABC ]', ['EN/ABC']],
            ['A folder [EN/EN/ABC]', ['EN/EN/ABC']],
            ['A folder [ EN/EN/ABC]', ['EN/EN/ABC']],
            ['A folder [EN/EN/ABC ]', ['EN/EN/ABC']],
            ['A folder [ EN/EN/ABC ]', ['EN/EN/ABC']],
        ];
    }

    public static function providerMultiple(): array
    {
        return [
            ['A folder [ABC,XYZ]', ['ABC', 'XYZ']],
            ['A folder [ ABC, XYZ]', ['ABC', 'XYZ']],
            ['A folder [ABC ,XYZ ]', ['ABC', 'XYZ']],
            ['A folder [ ABC , XYZ ]', ['ABC', 'XYZ']],
            ['A folder [EN/ABC,xxx]', ['EN/ABC', 'xxx']],
            ['A folder [ EN/ABC, xxx]', ['EN/ABC', 'xxx']],
            ['A folder [EN/ABC ,xxx ]', ['EN/ABC', 'xxx']],
            ['A folder [ EN/ABC , xxx ]', ['EN/ABC', 'xxx']],
            ['A folder [EN/EN/ABC,xxx]', ['EN/EN/ABC', 'xxx']],
            ['A folder [ EN/EN/ABC, xxx]', ['EN/EN/ABC', 'xxx']],
            ['A folder [EN/EN/ABC ,xxx ]', ['EN/EN/ABC', 'xxx']],
            ['A folder [ EN/EN/ABC , xxx ]', ['EN/EN/ABC', 'xxx']],
            ['A folder [EN/ABC,EN/EN/ABC,xxx]', ['EN/ABC', 'EN/EN/ABC', 'xxx']],
            ['A folder [ EN/ABC, EN/EN/ABC, xxx]', ['EN/ABC', 'EN/EN/ABC', 'xxx']],
            ['A folder [EN/ABC ,EN/EN/ABC ,xxx ]', ['EN/ABC', 'EN/EN/ABC', 'xxx']],
            ['A folder [ EN/ABC , EN/EN/ABC , xxx ]', ['EN/ABC', 'EN/EN/ABC', 'xxx']],
        ];
    }
}
