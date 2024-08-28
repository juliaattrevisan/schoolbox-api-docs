<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Folder\Code\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Resource\Folder\Code\IClassMatcher;
use Schoolbox\Resource\Folder\Code\ClassCodeMatchFactory;

/**
 * Test that the IClassMatcher implementations behave as expected
 * @see IClassMatcher
 * @codeCoverageIgnore
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\Resource\Folder\Code\ClassMatcherBase::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\External\Synergetic\ClassMatcher::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\External\Pcschools\ClassMatcher::class)]
class ClassMatcherTest extends TestCase
{
    private static $factory;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$factory = new ClassCodeMatchFactory();
    }

    /**
     * @param string      $folderCode
     * @param string|null $sisCampus
     * @param string      $sisClass
     * @param bool        $isMatch
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerDefault')]
    public function testDefault(string $folderCode, ?string $sisCampus, string $sisClass, bool $isMatch): void
    {
        $matcher = self::$factory->build('');

        $this->assertEquals($isMatch, $matcher->isMatch($folderCode, $sisCampus, $sisClass));
    }

    /**
     * @param string      $folderCode
     * @param string|null $sisCampus
     * @param string      $sisClass
     * @param bool        $isMatch
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerSynergetic')]
    public function testSynergetic(string $folderCode, ?string $sisCampus, string $sisClass, bool $isMatch): void
    {
        $matcher = self::$factory->build('synergetic');

        $this->assertEquals($isMatch, $matcher->isMatch($folderCode, $sisCampus, $sisClass));
    }

    /**
     * @param string      $folderCode
     * @param string|null $sisCampus
     * @param string      $sisClass
     * @param bool        $isMatch
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerPcschool')]
    public function testPcschool(string $folderCode, ?string $sisCampus, string $sisClass, bool $isMatch): void
    {
        $matcher = self::$factory->build('pcschools');

        $this->assertEquals($isMatch, $matcher->isMatch($folderCode, $sisCampus, $sisClass));
    }


    // Data Providers

    public static function providerDefault(): array
    {
        /** @noinspection DuplicatedCode */
        // string $folderCode, ?string $sisCampus, string $sisClass, bool $isMatch
        return [
            // No campus in folder code
            'folder code w/o campus: sisClass matches, sisCampus null'                => ['ABC', null, 'ABC', true],
            'folder code w/o campus: sisClass matches, sisCampus empty'               => ['ABC', '', 'ABC', true],
            'folder code w/o campus: sisClass has campus, sisCampus not empty'        => ['ABC', 'D', 'D/ABC', false],
            'folder code w/o campus: sisClass has campus, sisCampus empty'            => ['ABC', '', 'D/ABC', false],
            'folder code w/o campus: sisClass has campus, sisCampus null'             => ['ABC', null, 'D/ABC', false],

            // Campus in folder code
            'folder code w/ campus: sisClass matches, sisCampus null'                 => ['D/ABC', null, 'D/ABC', true],
            'folder code w/ campus: sisClass matches, sisCampus empty'                => ['D/ABC', '', 'D/ABC', true],
            'folder code w/ campus: sisClass has other campus, sisCampus empty'       => ['D/ABC', '', 'E/ABC', false],
            'folder code w/ campus: sisCampus/sisClass matches'                       => ['D/ABC', 'D', 'ABC', true],
            'folder code w/ campus: sisClass matches, sisCampus redundant'            => ['D/ABC', 'D', 'D/ABC', true],

            // Campus in folder code, multiple separators
            'folder code w/ / and campus: sisClass matches, sisCampus null'           => ['D/Y/ABC', null, 'D/Y/ABC', true],
            'folder code w/ / and campus: sisClass matches, sisCampus empty'          => ['D/Y/ABC', '', 'D/Y/ABC', true],
            'folder code w/ / and campus: sisCampus/sisClass matches'                 => ['D/Y/ABC', 'D', 'Y/ABC', true],
            'folder code w/ / and campus: sisCampus/sisClass matches, sisClass has /' => ['D/Y/ABC', 'D/Y', 'ABC', true],
            'folder code w/ / and campus: sisClass matches, sisCampus redundant'      => ['D/Y/ABC', 'D', 'D/Y/ABC', true],
            'folder code w/ / and campus: sisClass matches, sisCampus incomplete'     => ['D/Y/ABC', 'D', 'ABC', false],

            // Case insensitive
            // No campus in folder code
            'folder code w/o campus: sisClass matches, sisCampus null (case insensitive)'                => ['Abc', null, 'ABC', true],
            'folder code w/o campus: sisClass matches, sisCampus empty (case insensitive)'               => ['ABC', '', 'Abc', true],
            'folder code w/o campus: sisClass has campus, sisCampus not empty (case insensitive)'        => ['ABC', 'D', 'd/ABC', false],
            'folder code w/o campus: sisClass has campus, sisCampus empty (case insensitive)'            => ['ABC', '', 'D/Abc', false],
            'folder code w/o campus: sisClass has campus, sisCampus null (case insensitive)'             => ['Abc', null, 'D/ABC', false],

            // Campus in folder code
            'folder code w/ campus: sisClass matches, sisCampus null (case insensitive)'                 => ['D/Abc', null, 'D/ABC', true],
            'folder code w/ campus: sisClass matches, sisCampus empty (case insensitive)'                => ['D/ABC', '', 'd/ABC', true],
            'folder code w/ campus: sisClass has other campus, sisCampus empty (case insensitive)'       => ['D/ABC', '', 'E/Abc', false],
            'folder code w/ campus: sisCampus/sisClass matches (case insensitive)'                       => ['D/ABC', 'D', 'Abc', true],
            'folder code w/ campus: sisClass matches, sisCampus redundant (case insensitive)'            => ['D/Abc', 'D', 'D/ABC', true],

            // Campus in folder code, multiple separators
            'folder code w/ / and campus: sisClass matches, sisCampus null (case insensitive)'           => ['D/Y/Abc', null, 'D/Y/ABC', true],
            'folder code w/ / and campus: sisClass matches, sisCampus empty (case insensitive)'          => ['D/Y/ABC', '', 'D/Y/Abc', true],
            'folder code w/ / and campus: sisCampus/sisClass matches (case insensitive)'                 => ['D/Y/Abc', 'D', 'Y/ABC', true],
            'folder code w/ / and campus: sisCampus/sisClass matches, sisClass has / (case insensitive)' => ['D/Y/ABC', 'D/Y', 'Abc', true],
            'folder code w/ / and campus: sisClass matches, sisCampus redundant (case insensitive)'      => ['d/y/ABC', 'D', 'D/Y/ABC', true],
            'folder code w/ / and campus: sisClass matches, sisCampus incomplete (case insensitive)'     => ['D/Y/Abc', 'D', 'ABC', false],

            // PCSchool case: a space between campus code separator and class name
            'folder code w/ campus: sisCampus/sisClass matches, space in folderCode'                     => ['D/ 9ART', 'D', '9ART', false],
            'folder code w/ campus: sisCampus/sisClass matches, space in folderCode and sisClass'        => ['D/ 9ART', 'D', ' 9ART', false],
        ];
    }

    public static function providerSynergetic(): array
    {
        /** @noinspection DuplicatedCode */
        // string $folderCode, ?string $sisCampus, string $sisClass, bool $isMatch
        return [
            // No campus in folder code
            'folder code w/o campus: sisClass matches, sisCampus null'                => ['ABC', null, 'ABC', true],
            'folder code w/o campus: sisClass matches, sisCampus empty'               => ['ABC', '', 'ABC', true],
            'folder code w/o campus: sisClass has campus, sisCampus not empty'        => ['ABC', 'D', 'D/ABC', false],
            'folder code w/o campus: sisClass has campus, sisCampus empty'            => ['ABC', '', 'D/ABC', false],
            'folder code w/o campus: sisClass has campus, sisCampus null'             => ['ABC', null, 'D/ABC', false],

            // Campus in folder code
            'folder code w/ campus: sisClass matches, sisCampus null'                 => ['D/ABC', null, 'D/ABC', true],
            'folder code w/ campus: sisClass matches, sisCampus empty'                => ['D/ABC', '', 'D/ABC', true],
            'folder code w/ campus: sisClass has other campus, sisCampus empty'       => ['D/ABC', '', 'E/ABC', false],
            'folder code w/ campus: sisCampus/sisClass matches'                       => ['D/ABC', 'D', 'ABC', true],
            'folder code w/ campus: sisClass matches, sisCampus redundant'            => ['D/ABC', 'D', 'D/ABC', true],  // fuzzy

            // Campus in folder code, multiple separators
            'folder code w/ / and campus: sisClass matches, sisCampus null'           => ['D/Y/ABC', null, 'D/Y/ABC', true],
            'folder code w/ / and campus: sisClass matches, sisCampus empty'          => ['D/Y/ABC', '', 'D/Y/ABC', true],
            'folder code w/ / and campus: sisCampus/sisClass matches'                 => ['D/Y/ABC', 'D', 'Y/ABC', true],
            'folder code w/ / and campus: sisCampus/sisClass matches, sisClass has /' => ['D/Y/ABC', 'D/Y', 'ABC', true],
            'folder code w/ / and campus: sisClass matches, sisCampus redundant'      => ['D/Y/ABC', 'D', 'D/Y/ABC', true],  // fuzzy
            'folder code w/ / and campus: sisClass matches, sisCampus incomplete'     => ['D/Y/ABC', 'D', 'ABC', false],

            // Case insensitive
            // No campus in folder code
            'folder code w/o campus: sisClass matches, sisCampus null (case insensitive)'                => ['Abc', null, 'ABC', true],
            'folder code w/o campus: sisClass matches, sisCampus empty (case insensitive)'               => ['ABC', '', 'Abc', true],
            'folder code w/o campus: sisClass has campus, sisCampus not empty (case insensitive)'        => ['ABC', 'D', 'd/ABC', false],
            'folder code w/o campus: sisClass has campus, sisCampus empty (case insensitive)'            => ['ABC', '', 'D/Abc', false],
            'folder code w/o campus: sisClass has campus, sisCampus null (case insensitive)'             => ['Abc', null, 'D/ABC', false],

            // Campus in folder code
            'folder code w/ campus: sisClass matches, sisCampus null (case insensitive)'                 => ['D/Abc', null, 'D/ABC', true],
            'folder code w/ campus: sisClass matches, sisCampus empty (case insensitive)'                => ['D/ABC', '', 'd/ABC', true],
            'folder code w/ campus: sisClass has other campus, sisCampus empty (case insensitive)'       => ['D/ABC', '', 'E/Abc', false],
            'folder code w/ campus: sisCampus/sisClass matches (case insensitive)'                       => ['D/ABC', 'D', 'Abc', true],
            'folder code w/ campus: sisClass matches, sisCampus redundant (case insensitive)'            => ['d/Abc', 'D', 'D/ABC', true],  // fuzzy

            // Campus in folder code, multiple separators
            'folder code w/ / and campus: sisClass matches, sisCampus null (case insensitive)'           => ['D/Y/Abc', null, 'D/Y/ABC', true],
            'folder code w/ / and campus: sisClass matches, sisCampus empty (case insensitive)'          => ['D/Y/ABC', '', 'D/Y/Abc', true],
            'folder code w/ / and campus: sisCampus/sisClass matches (case insensitive)'                 => ['D/Y/Abc', 'd', 'Y/ABC', true],
            'folder code w/ / and campus: sisCampus/sisClass matches, sisClass has / (case insensitive)' => ['D/Y/ABC', 'D/y', 'Abc', true],
            'folder code w/ / and campus: sisClass matches, sisCampus redundant (case insensitive)'      => ['D/Y/ABC', 'D', 'D/Y/Abc', true],  // fuzzy
            'folder code w/ / and campus: sisClass matches, sisCampus incomplete (case insensitive)'     => ['D/Y/Abc', 'd', 'ABC', false],

            // PCSchool case: a space between campus code separator and class name
            'folder code w/ campus: sisCampus/sisClass matches, space in folderCode'                     => ['D/ 9ART', 'D', '9ART', false],
            'folder code w/ campus: sisCampus/sisClass matches, space in folderCode and sisClass'        => ['D/ 9ART', 'D', ' 9ART', false],
        ];
    }

    public static function providerPcschool(): array
    {
        /** @noinspection DuplicatedCode */
        // string $folderCode, ?string $sisCampus, string $sisClass, bool $isMatch
        return [
            // No campus in folder code
            'folder code w/o campus: sisClass matches, sisCampus null'                => ['ABC', null, 'ABC', true],
            'folder code w/o campus: sisClass matches, sisCampus empty'               => ['ABC', '', 'ABC', true],
            'folder code w/o campus: sisClass has campus, sisCampus not empty'        => ['ABC', 'D', 'D/ABC', false],
            'folder code w/o campus: sisClass has campus, sisCampus empty'            => ['ABC', '', 'D/ABC', false],
            'folder code w/o campus: sisClass has campus, sisCampus null'             => ['ABC', null, 'D/ABC', false],

            // Campus in folder code
            'folder code w/ campus: sisClass matches, sisCampus null'                 => ['D/ABC', null, 'D/ABC', true],
            'folder code w/ campus: sisClass matches, sisCampus empty'                => ['D/ABC', '', 'D/ABC', true],
            'folder code w/ campus: sisClass has other campus, sisCampus empty'       => ['D/ABC', '', 'E/ABC', false],
            'folder code w/ campus: sisCampus/sisClass matches'                       => ['D/ABC', 'D', 'ABC', true],
            'folder code w/ campus: sisClass matches, sisCampus redundant'            => ['D/ABC', 'D', 'D/ABC', true],

            // Campus in folder code, multiple separators
            'folder code w/ / and campus: sisClass matches, sisCampus null'           => ['D/Y/ABC', null, 'D/Y/ABC', true],
            'folder code w/ / and campus: sisClass matches, sisCampus empty'          => ['D/Y/ABC', '', 'D/Y/ABC', true],
            'folder code w/ / and campus: sisCampus/sisClass matches'                 => ['D/Y/ABC', 'D', 'Y/ABC', true],
            'folder code w/ / and campus: sisCampus/sisClass matches, sisClass has /' => ['D/Y/ABC', 'D/Y', 'ABC', true],
            'folder code w/ / and campus: sisClass matches, sisCampus redundant'      => ['D/Y/ABC', 'D', 'D/Y/ABC', true],
            'folder code w/ / and campus: sisClass matches, sisCampus incomplete'     => ['D/Y/ABC', 'D', 'ABC', false],

            // Case insensitive
            // No campus in folder code
            'folder code w/o campus: sisClass matches, sisCampus null (case insensitive)'                => ['Abc', null, 'ABC', true],
            'folder code w/o campus: sisClass matches, sisCampus empty (case insensitive)'               => ['ABC', '', 'Abc', true],
            'folder code w/o campus: sisClass has campus, sisCampus not empty (case insensitive)'        => ['ABC', 'D', 'd/ABC', false],
            'folder code w/o campus: sisClass has campus, sisCampus empty (case insensitive)'            => ['ABC', '', 'D/Abc', false],
            'folder code w/o campus: sisClass has campus, sisCampus null (case insensitive)'             => ['Abc', null, 'D/ABC', false],

            // Campus in folder code
            'folder code w/ campus: sisClass matches, sisCampus null (case insensitive)'                 => ['D/Abc', null, 'D/ABC', true],
            'folder code w/ campus: sisClass matches, sisCampus empty (case insensitive)'                => ['D/ABC', '', 'd/ABC', true],
            'folder code w/ campus: sisClass has other campus, sisCampus empty (case insensitive)'       => ['D/ABC', '', 'E/Abc', false],
            'folder code w/ campus: sisCampus/sisClass matches (case insensitive)'                       => ['D/ABC', 'D', 'Abc', true],
            'folder code w/ campus: sisClass matches, sisCampus redundant (case insensitive)'            => ['D/Abc', 'D', 'D/ABC', true],

            // Campus in folder code, multiple separators
            'folder code w/ / and campus: sisClass matches, sisCampus null (case insensitive)'           => ['D/Y/Abc', null, 'D/Y/ABC', true],
            'folder code w/ / and campus: sisClass matches, sisCampus empty (case insensitive)'          => ['D/Y/ABC', '', 'D/Y/Abc', true],
            'folder code w/ / and campus: sisCampus/sisClass matches (case insensitive)'                 => ['D/Y/Abc', 'D', 'Y/ABC', true],
            'folder code w/ / and campus: sisCampus/sisClass matches, sisClass has / (case insensitive)' => ['D/Y/ABC', 'D/Y', 'Abc', true],
            'folder code w/ / and campus: sisClass matches, sisCampus redundant (case insensitive)'      => ['d/y/ABC', 'D', 'D/Y/ABC', true],
            'folder code w/ / and campus: sisClass matches, sisCampus incomplete (case insensitive)'     => ['D/Y/Abc', 'D', 'ABC', false],

            // PCSchool case: a space between campus code separator and class name
            'folder code w/ campus: sisCampus/sisClass matches, space in folderCode'                     => ['D/ 9ART', 'D', '9ART', true],
            'folder code w/ campus: sisCampus/sisClass matches, space in folderCode and sisClass'        => ['D/ 9ART', 'D', ' 9ART', true],
        ];
    }
}
