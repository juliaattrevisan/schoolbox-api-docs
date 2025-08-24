<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\API\Token\JwtParser\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Schoolbox\API\Token\JwtParser\IParser;
use Schoolbox\API\Token\JwtParser\OauthClientHeld;
use Schoolbox\Config\ArrayLoader;
use Schoolbox\Oidc\Server\RelyingParty;
use Schoolbox\UserMapper;

#[CoversClass(OauthClientHeld::class)]
class OauthClientHeldTest extends TestCase
{
    use CreateJwt;
    use CreateLogger;

    #[DataProvider('getTestAcceptsData')]
    public function testAccepts(array $claims, bool $expected): void
    {
        $this->assertSame($expected, $this->createParser()->accepts($this->createJwt($claims)));
    }

    /**
     * Data provider for `testAccepts`
     *
     * @return iterable
     */
    public static function getTestAcceptsData(): iterable
    {
        yield 'no audience claim' => [
            [],
            false,
        ];

        yield 'null audience claim' => [
            [
                IParser::CLAIM_AUD => null,
            ],
            false,
        ];

        yield 'single audience claim: matching relying party' => [
            [
                IParser::CLAIM_AUD => 'test',
            ],
            true,
        ];

        yield 'single audience claim: no matching relying party' => [
            [
                IParser::CLAIM_AUD => 'test2',
            ],
            false,
        ];

        yield 'multiple audience claim: matching relying party' => [
            [
                IParser::CLAIM_AUD => ['test', 'test2'],
            ],
            true,
        ];

        yield 'multiple audience claim: no matching relying party' => [
            [
                IParser::CLAIM_AUD => ['test2', 'test3'],
            ],
            false,
        ];
    }

    /**
     * Create the parser, with the provided config variables.
     *
     * @return OauthClientHeld
     */
    private function createParser(): OauthClientHeld
    {
        return new OauthClientHeld(
            $this->createMock(UserMapper::class),
            new ArrayLoader([]),
            $this->createLogger(self::class),
            $this->createMock(RelyingParty\Mapper::class),
        );
    }
}
