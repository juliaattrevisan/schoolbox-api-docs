<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\User\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\LTI\User\Codec;
use Schoolbox\User;
use Schoolbox\UserMapper;

#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\LTI\User\Codec::class)]
class CodecTest extends TestCase
{
    /**
     * User codec being tested.
     */
    private Codec $userCodec;

    /**
     * Set up the user codec, with a stubbed user mapper.
     */
    public function setUp(): void
    {
        $userMapper = $this->createStub(UserMapper::class);
        $userMapper
            ->method('findByUsername')
            ->willReturnCallback(
                static function ($username) {
                    switch ($username) {
                        case 'user1':
                            return (new User())->setId(1)->setUsername('user1');
                        case 'user2':
                        default:
                            return null;
                    }
                }
            );
        $this->userCodec = new Codec($userMapper);
    }

    /**
     * @param User|null   $user
     * @param string|null $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestEncodeData')]
    public function testEncode(?User $user, ?string $expected): void
    {
        $this->assertSame($expected, $this->userCodec->encode($user));
    }

    /**
     * Data provider for `testEncode`.
     *
     * @return iterable
     */
    public static function getTestEncodeData(): iterable
    {
        yield 'Null user' => [
            null,
            null,
        ];
        yield 'User exists' => [
            (new User())->setId(1)->setUsername('user1'),
            'user1',
        ];
        yield 'User does not exist' => [
            (new User())->setId(null)->setUsername('user2'),
            'user2',
        ];
    }

    /**
     * @param string|null $encoded
     * @param User|null   $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestDecodeData')]
    public function testDecode(?string $encoded, ?User $expected): void
    {
        $decoded = $this->userCodec->decode($encoded);
        if (!$expected) {
            $this->assertNull($decoded);
        } else {
            $this->assertSame($expected->getId(), $decoded->getId());
            $this->assertSame($expected->getUsername(), $decoded->getUsername());
        }
    }

    /**
     * Data provider for `testDecode`.
     *
     * @return iterable
     */
    public static function getTestDecodeData(): iterable
    {
        yield 'Null user' => [
            null,
            null,
        ];
        yield 'User exists' => [
            'user1',
            (new User())->setId(1)->setUsername('user1'),
        ];
        yield 'User does not exist' => [
            'user2',
            null,
        ];
    }
}
