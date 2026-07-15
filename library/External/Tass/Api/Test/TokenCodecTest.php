<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Tass\Api\Test;

use External\Tass\Api\Exception\InvalidApiKey;
use PHPUnit;
use PHPUnit\Framework\TestCase;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Codec\Token;

#[\PHPUnit\Framework\Attributes\CoversClass(Token::class)]
class TokenCodecTest extends TestCase
{
    // ======================= Tests =========================== //
    /**
     * @param string $invalidApiKey
     * @return void
     * @throws PHPUnit\Framework\Exception
     * @throws InvalidApiKey
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerInvalidApiKey')]
    public function testInvalidApiKey(string $invalidApiKey): void
    {
        $this->expectException(InvalidApiKey::class);
        new Token($invalidApiKey);
    }

    // ======================= Data Providers =========================== //

    public static function providerInvalidApiKey(): array
    {
        return [
            [''],
            ['ab cd'],
            ['@dsfsd'],
            ['SSBhbSB0b2tlbg'],
            [' SSBhbSB0b2tlbg=='],
            ['SSBhbSB0b2tlbg== '],
        ];
    }
}
