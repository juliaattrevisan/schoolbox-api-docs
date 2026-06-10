<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\API\Token\JwtParser\Test;

use Monolog\Handler\HandlerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Schoolbox\API\Token\JwtParser\AudienceMatchMode;
use Schoolbox\API\Token\JwtParser\OtherInstanceIssued;
use Schoolbox\Config\ArrayLoader;
use Schoolbox\UserMapper;

#[CoversClass(OtherInstanceIssued::class)]
class OtherInstanceIssuedTest extends TestCase
{
    use CreateJwt;
    use CreateLogger;

    #[DataProvider('getTestAcceptsData')]
    public function testAccepts(array $claims, bool $expected): void
    {
        $handler = $this->createHandler();
        $this->assertSame($expected, $this->createParser($handler)->accepts($this->createJwt($claims)));
    }

    /**
     * Data provider for `testAccepts`
     *
     * @return iterable
     */
    public static function getTestAcceptsData(): iterable
    {
        yield 'custom instance ID claim exists' => [
            [
                OtherInstanceIssued::CLAIM_INSTANCE_ID => 'test',
            ],
            true,
        ];

        yield 'custom instance ID claim does not exist' => [
            [],
            false,
        ];

        yield 'custom instance ID claim is null' => [
            [
                OtherInstanceIssued::CLAIM_INSTANCE_ID => null,
            ],
            false,
        ];
    }

    /**
     * Create the parser.
     *
     * @param HandlerInterface $handler
     * @return OtherInstanceIssued
     */
    private function createParser(HandlerInterface $handler): OtherInstanceIssued
    {
        return new OtherInstanceIssued(
            $this->createMock(UserMapper::class),
            new ArrayLoader([]),
            $this->createLogger($handler),
            AudienceMatchMode::WARN,
        );
    }
}
