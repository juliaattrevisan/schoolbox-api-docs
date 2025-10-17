<?php

/*
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\OpenAI\test;

use JsonException;
use PHPUnit\Framework\TestCase;
use Schoolbox\Admin\OpenAI\Config;
use Schoolbox\Editor\OpenAI\Helper;

/**
 * Class ConfigTest
 *
 * Tests for Schoolbox\Admin\OpenAI\Config
 */
class ConfigTest extends TestCase
{
    /**
     * Data provider for migrate() tests
     *
     * @return array
     */
    public static function providerMigrate(): array
    {
        return [
            'OpenAI complete' => [
                Helper::ENDPOINT_TYPE_OPENAI,
                'openai-key-123',
                null,
                null,
                'gpt-4o',
                'System prompt',
                [
                    'provider' => Helper::ENDPOINT_TYPE_OPENAI,
                    'openai' => [
                        Config::FIELD_OPENAIKEY => ['value' => 'openai-key-123'],
                        Config::FIELD_MODEL => ['value' => 'gpt-4o'],
                        Config::FIELD_SYSTEM_PROMPT => ['value' => 'System prompt'],
                    ],
                    'azure' => [
                        Config::FIELD_RESOURCE_NAME => ['value' => ''],
                        Config::FIELD_DEPLOYMENT_ID => ['value' => ''],
                    ],
                ],
            ],
            'OpenAI missing model => NOT_ACTIVE' => [
                Helper::ENDPOINT_TYPE_OPENAI,
                'openai-key-xyz',
                null,
                null,
                null,
                null,
                [
                    'provider' => Helper::ENDPOINT_TYPE_OPENAI,
                    'openai' => [
                        Config::FIELD_OPENAIKEY => ['value' => 'openai-key-xyz'],
                        Config::FIELD_MODEL => ['value' => Config::NOT_ACTIVE],
                        Config::FIELD_SYSTEM_PROMPT => ['value' => ''],
                    ],
                    'azure' => [
                        Config::FIELD_RESOURCE_NAME => ['value' => ''],
                        Config::FIELD_DEPLOYMENT_ID => ['value' => ''],
                    ],
                ],
            ],
            'Azure complete' => [
                Helper::ENDPOINT_TYPE_AZURE,
                'shared-openai-key',
                'my-azure-resource',
                'deployment-123',
                null,
                'Azure system prompt',
                [
                    'provider' => Helper::ENDPOINT_TYPE_AZURE,
                    'openai' => [
                        Config::FIELD_OPENAIKEY => ['value' => 'shared-openai-key'],
                        Config::FIELD_MODEL => ['value' => ''],
                        Config::FIELD_SYSTEM_PROMPT => ['value' => 'Azure system prompt'],
                    ],
                    'azure' => [
                        Config::FIELD_RESOURCE_NAME => ['value' => 'my-azure-resource'],
                        Config::FIELD_DEPLOYMENT_ID => ['value' => 'deployment-123'],
                    ],
                ],
            ],
            'Provider off (defaults)' => [
                null,
                null,
                null,
                null,
                null,
                null,
                [
                    'provider' => Config::NO_PROVIDER,
                    'openai' => [
                        Config::FIELD_OPENAIKEY => ['value' => ''],
                        Config::FIELD_MODEL => ['value' => ''],
                        Config::FIELD_SYSTEM_PROMPT => ['value' => ''],
                    ],
                    'azure' => [
                        Config::FIELD_RESOURCE_NAME => ['value' => ''],
                        Config::FIELD_DEPLOYMENT_ID => ['value' => ''],
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider providerMigrate
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerMigrate')]
    public function testMigrate(
        ?string $provider,
        ?string $key,
        ?string $resourceName,
        ?string $deploymentId,
        ?string $model,
        ?string $systemPrompt,
        array $expected
    ): void {
        $cfg = new Config();
        $result = $cfg->migrate($provider, $key, $resourceName, $deploymentId, $model, $systemPrompt);

        // provider
        $this->assertArrayHasKey(Config::FIELD_PROVIDER, $result);
        $this->assertEquals($expected['provider'], $result[Config::FIELD_PROVIDER]);

        // OpenAI block
        $this->assertArrayHasKey(Helper::ENDPOINT_TYPE_OPENAI, $result);
        $this->assertEquals(
            $expected['openai'][Config::FIELD_OPENAIKEY]['value'],
            $result[Helper::ENDPOINT_TYPE_OPENAI][Config::FIELD_OPENAIKEY]['value']
        );
        $this->assertEquals(
            $expected['openai'][Config::FIELD_MODEL]['value'],
            $result[Helper::ENDPOINT_TYPE_OPENAI][Config::FIELD_MODEL]['value']
        );
        $this->assertEquals(
            $expected['openai'][Config::FIELD_SYSTEM_PROMPT]['value'],
            $result[Helper::ENDPOINT_TYPE_OPENAI][Config::FIELD_SYSTEM_PROMPT]['value']
        );

        // Azure block
        $this->assertArrayHasKey(Helper::ENDPOINT_TYPE_AZURE, $result);
        $this->assertEquals(
            $expected['azure'][Config::FIELD_RESOURCE_NAME]['value'],
            $result[Helper::ENDPOINT_TYPE_AZURE][Config::FIELD_RESOURCE_NAME]['value']
        );
        $this->assertEquals(
            $expected['azure'][Config::FIELD_DEPLOYMENT_ID]['value'],
            $result[Helper::ENDPOINT_TYPE_AZURE][Config::FIELD_DEPLOYMENT_ID]['value']
        );
    }

    /**
     * Data provider for isEnabled
     *
     * @return array
     */
    public static function providerIsEnabled(): array
    {
        return [
            'OpenAI enabled' => [
                [Config::FIELD_PROVIDER => Helper::ENDPOINT_TYPE_OPENAI],
                true,
            ],
            'Azure enabled' => [
                [Config::FIELD_PROVIDER => Helper::ENDPOINT_TYPE_AZURE],
                true,
            ],
            'Provider off' => [
                [Config::FIELD_PROVIDER => Config::NO_PROVIDER],
                false,
            ],
        ];
    }

    /**
     * @dataProvider providerIsEnabled
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerIsEnabled')]
    public function testIsEnabled(?array $config, bool $expected): void
    {
        $cfg = new Config();
        $this->assertSame($expected, $cfg->isEnabled($config));
    }

    /**
     * Data provider for decode
     *
     * @return array
     */
    public static function providerDecode(): array
    {
        $example = [
            Config::FIELD_PROVIDER => Helper::ENDPOINT_TYPE_OPENAI,
            Helper::ENDPOINT_TYPE_OPENAI => [
                Config::FIELD_OPENAIKEY => ['value' => 'k'],
                Config::FIELD_MODEL => ['value' => 'gpt-4o'],
                Config::FIELD_SYSTEM_PROMPT => ['value' => 'sp'],
            ],
            Helper::ENDPOINT_TYPE_AZURE => [
                Config::FIELD_RESOURCE_NAME => ['value' => ''],
                Config::FIELD_DEPLOYMENT_ID => ['value' => ''],
            ],
        ];

        return [
            'valid json' => [
                json_encode($example, JSON_THROW_ON_ERROR),
                $example,
            ],
            'null returns null' => [
                null,
                null,
            ],
        ];
    }

    /**
     * @dataProvider providerDecode
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerDecode')]
    public function testDecode(?string $json, $expected): void
    {
        $cfg = new Config();

        if ($json === null) {
            $this->assertNull($cfg->decode(null));
            return;
        }

        $decoded = $cfg->decode($json);
        $this->assertEquals($expected, $decoded);
    }

    public function testDecodeThrowsOnInvalidJson(): void
    {
        $cfg = new Config();
        $this->expectException(JsonException::class);
        // invalid JSON
        $cfg->decode('{"invalid": }');
    }
}
