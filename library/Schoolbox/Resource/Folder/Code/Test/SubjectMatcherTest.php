<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Resource\Folder\Code\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Resource\Folder\Code\ISubjectMatcher;
use Schoolbox\Resource\Folder\Code\SubjectCodeMatchFactory;

/**
 * Test that the ISubjectMatcher implementations behave as expected
 * @see ISubjectMatcher
 */
class SubjectMatcherTest extends TestCase
{
    private static $factory;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        self::$factory = new SubjectCodeMatchFactory();
    }

    /**
     * @covers       SubjectMatcher::isMatch
     * @dataProvider providerDefault
     * @param string      $folderCode
     * @param string|null $sisCampus
     * @param string      $sisClass
     * @param bool        $isMatch
     */
    public function testDefault(string $folderCode, ?string $sisCampus, string $sisClass, bool $isMatch): void
    {
        $matcher = self::$factory->build('');

        $this->assertEquals($isMatch, $matcher->isMatch($folderCode, $sisCampus, $sisClass));
    }

    /**
     * @covers       \External\Synergetic\SubjectMatcher::isMatch
     * @dataProvider providerSynergetic
     * @param string      $folderCode
     * @param string|null $sisCampus
     * @param string      $sisClass
     * @param bool        $isMatch
     */
    public function testSynergetic(string $folderCode, ?string $sisCampus, string $sisClass, bool $isMatch): void
    {
        $matcher = self::$factory->build('synergetic');

        $this->assertEquals($isMatch, $matcher->isMatch($folderCode, $sisCampus, $sisClass));
    }


    // Data Providers

    public function providerDefault(): array
    {
        /** @noinspection DuplicatedCode */
        // string $folderCode, ?string $sisCampus, string $sisClass, bool $isMatch
        return [
            ['D/ABC', 'D', 'ABC', true],
            ['D/ABC', 'D', 'D/ABC', false],
            ['D/ABC', '', 'D/ABC', true],
            ['D/ABC', null, 'D/ABC', true],
            ['ABC', '', 'ABC', true],
            ['ABC', 'D', 'D/ABC', false],
            ['ABC', '', 'D/ABC', false],
            ['ABC', null, 'D/ABC', false],
            ['D/Y/ABC', null, 'D/Y/ABC', true],
            ['D/Y/ABC', '', 'D/Y/ABC', true],
            ['D/Y/ABC', 'D', 'D/Y/ABC', false],
            ['D/Y/ABC', 'D', 'Y/ABC', true],
            ['D/Y/ABC', 'D', 'ABC', false],
        ];
    }

    public function providerSynergetic(): array
    {
        /** @noinspection DuplicatedCode */
        // string $folderCode, ?string $sisCampus, string $sisClass, bool $isMatch
        return [
            ['D/ABC', 'D', 'ABC', true],
            ['D/ABC', 'D', 'D/ABC', true],  // fuzzy
            ['D/ABC', '', 'D/ABC', true],
            ['D/ABC', null, 'D/ABC', true],
            ['ABC', '', 'ABC', true],
            ['ABC', 'D', 'D/ABC', false],
            ['ABC', '', 'D/ABC', false],
            ['ABC', null, 'D/ABC', false],
            ['D/Y/ABC', null, 'D/Y/ABC', true],
            ['D/Y/ABC', '', 'D/Y/ABC', true],
            ['D/Y/ABC', 'D', 'D/Y/ABC', true],  // fuzzy
            ['D/Y/ABC', 'D', 'Y/ABC', true],
            ['D/Y/ABC', 'D', 'ABC', false],
        ];
    }
}
