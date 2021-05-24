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

/**
 * @covers \External\Tass\Api\TokenCodec
 */
class TokenCodecTest extends TestCase
{

    // ======================= Tests =========================== //

    /**
     * @dataProvider provider_invalidApiKey
     * @param string $invalidApiKey
     * @return void
     * @throws PHPUnit\Framework\Exception
     * @throws InvalidApiKey
     */
    public function test_invalidApiKey(string $invalidApiKey): void
    {
        $this->expectException(InvalidApiKey::class);
        new Token($invalidApiKey);
    }


    /**
     * @dataProvider provider_reversibility
     * @param Token $codec
     * @param array $data
     * @return void
     */
    public function test_reversibility(Token $codec, array $data): void
    {
        $unpacked = $codec->unpackToken($codec->generateToken($data));
        $this->assertSame($data, $unpacked);
    }


    // ======================= Data Providers =========================== //

    public function provider_invalidApiKey(): array
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


    public function provider_reversibility(): array
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
