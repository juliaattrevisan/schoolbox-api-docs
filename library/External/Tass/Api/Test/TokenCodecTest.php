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

#[\PHPUnit\Framework\Attributes\CoversClass(\External\Tass\Api\TokenCodec::class)]
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


    /**
     * @param Token $codec
     * @param array $data
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerReversibility')]
    public function testReversibility(Token $codec, array $data): void
    {
        $unpacked = $codec->unpackToken($codec->generateToken($data, true));
        $this->assertSame($data, $unpacked);
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


    public function providerReversibility(): array
    {
        $tokenCodec = $this->getTokenCodec();

        return [
            [$tokenCodec, []],
            [$tokenCodec, ['key' => '']],
            [$tokenCodec, ['key' => 'value']],
            [$tokenCodec, ['key' => false]],
            [$tokenCodec, ['key' => null]],
            [$tokenCodec, ['key1' => 'value1', 'key2' => 'value2',]],
        ];
    }


    // ===================== Helpers =========================== //

    private function getTokenCodec(): Token
    {
        return new Token('SSBhbSB0b2tlbg==');
    }
}
