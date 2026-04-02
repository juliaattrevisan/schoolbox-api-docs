<?php

declare(strict_types=1);

namespace Schoolbox\Admin\DataRetention\Test;

use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use Schoolbox\Admin\DataRetention\ValidStrats;

/**
 * Class ValidStratsTest
 *
 * Thin coverage around data retention strat selection to guard behaviour.
 */
class ValidStratsTest extends TestCase
{
    /**
     * @param string   $cliArgs
     * @param string[] $expected
     * @throws ExpectationFailedException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerValidStrats')]
    public function testValidStrats(string $cliArgs, array $expected): void
    {
        $this->assertSame($expected, new ValidStrats($cliArgs)->getFilteredStrats());
    }

    /**
     * @return array<string, array{0:string,1:array}>
     */
    public static function providerValidStrats(): array
    {
        return [
            'empty string' => [
                '',
                [],
            ],
            'only commas / whitespace' => [
                '  , , , , , , ,, , ,,,,     ,, , ,,       ,           ',
                [],
            ],
            'single valid' => [
                'moments',
                ['moments'],
            ],
            'valid and invalid mixed' => [
                'moments,unknown,myFiles',
                ['moments', 'myFiles'],
            ],
            'invalid only' => [
                'foo,bar',
                [],
            ],
            'duplicates' => [
                'moments,moments,myFiles,moments',
                ['moments', 'myFiles'],
            ],
        ];
    }


    /**
     * @param string $strat
     * @param string $cliArgs
     * @param bool   $expected
     * @throws ExpectationFailedException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerStratEnabled')]
    public function testStratEnabled(string $strat, string $cliArgs, bool $expected): void
    {
        $validStrats = new ValidStrats($cliArgs);

        $this->assertSame($expected, $validStrats->isStratEnabled($strat));
    }


    /**
     * Data provider for testStratEnabled
     *
     * @return array<string, array{0:string,1:string,2:bool}>
     */
    public static function providerStratEnabled(): array
    {
        return [
            // Special case: when no strats provided we treat all as enabled
            'special case moments' => [
                'moments',
                '',
                true,
            ],

            'moments enabled' => [
                'moments',
                'moments',
                true,
            ],

            'myFiles enabled' => [
                'myFiles',
                'myFiles',
                true,
            ],

            'moments not enabled when only myFiles' => [
                'moments',
                'myFiles',
                false,
            ],

            'unknown strat not enabled when moments present' => [
                'unknown',
                'moments',
                false,
            ],
        ];
    }
}
