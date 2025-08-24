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

    /**
     * Test audience: the relying party mapper assumes there's a configured
     * relying party with this value as its client ID.
     */
    private const TEST_AUDIENCE = 'test';

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
                IParser::CLAIM_AUD => self::TEST_AUDIENCE,
            ],
            true,
        ];

        yield 'single audience claim: no matching relying party' => [
            [
                IParser::CLAIM_AUD => self::TEST_AUDIENCE . '2',
            ],
            false,
        ];

        yield 'multiple audience claim: matching relying party' => [
            [
                IParser::CLAIM_AUD => [self::TEST_AUDIENCE, self::TEST_AUDIENCE . '2'],
            ],
            true,
        ];

        yield 'multiple audience claim: no matching relying party' => [
            [
                IParser::CLAIM_AUD => [self::TEST_AUDIENCE . '2', self::TEST_AUDIENCE . '3'],
            ],
            false,
        ];
    }

    /**
     * Create the parser.
     *
     * @return OauthClientHeld
     */
    private function createParser(): OauthClientHeld
    {
        $mockRelyingPartyMapper = $this->createMock(RelyingParty\Mapper::class);
        $mockRelyingPartyMapper
            ->method('countByColumnList')
            ->willReturnCallback($this->mockCountByColumnList(...));
        return new OauthClientHeld(
            $this->createMock(UserMapper::class),
            new ArrayLoader([]),
            $this->createLogger(self::class),
            $mockRelyingPartyMapper,
        );
    }

    /**
     * Mock method for relying party mapper's `countByColumnList`.
     *
     * If searching for a client ID (this comes from the JWT's `aud` claim):
     * return 1 if one of the values is `test`.
     *
     * @param array $columns
     * @return int
     */
    private function mockCountByColumnList(array $columns): int
    {
        foreach ($columns as $name => $values) {
            if ($name === 'client_id') {
                $values = (array)$values;
                if (in_array(self::TEST_AUDIENCE, $values, true)) {
                    return 1;
                }
            }
        }
        return 0;
    }
}
