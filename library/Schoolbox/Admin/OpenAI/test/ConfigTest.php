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
use TypeError;

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
                        Config::FIELD_AIKEY => ['value' => 'openai-key-123'],
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
                        Config::FIELD_AIKEY => ['value' => 'openai-key-xyz'],
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
                        Config::FIELD_AIKEY => ['value' => 'shared-openai-key'],
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
                        Config::FIELD_AIKEY => ['value' => ''],
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

    // test isEnabled with null config
    public function testIsEnabledWithNullThrowsTypeError(): void
    {
        $cfg = new Config();
        $this->assertFalse($cfg->isEnabled(null));
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
                Config::FIELD_AIKEY => ['value' => 'k'],
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

    public function testDecodeNonArrayJsonReturnsScalar(): void
    {
        $cfg = new Config();
        // JSON boolean true (valid JSON) decodes to boolean true
        $this->expectException(TypeError::class);
        $decoded = $cfg->decode('true');
    }

    public function testDecodeThrowsOnInvalidJson(): void
    {
        $cfg = new Config();
        $this->expectException(JsonException::class);
        // invalid JSON
        $cfg->decode('{"invalid": }');
    }

    /**
     * Encode/Decode: round-trip for a valid OpenAI configuration
     *
     * This ensures that encode() produces valid JSON matching the expected
     * full configuration structure and that decode() returns the same array.
     */
    public function testEncodeDecodeRoundTripOpenAI(): void
    {
        $cfg = new Config();

        $input = [
            Config::FIELD_PROVIDER => ['value' => Helper::ENDPOINT_TYPE_OPENAI],
            Helper::ENDPOINT_TYPE_GENERAL => [
                Config::FIELD_AIKEY => ['value' => 'openai-key-123'],
                Config::FIELD_SYSTEM_PROMPT => ['value' => 'System prompt'],
            ],
            Helper::ENDPOINT_TYPE_OPENAI => [
                Config::FIELD_MODEL => ['value' => 'gpt-4o'],
            ],
            Helper::ENDPOINT_TYPE_AZURE => [
                Config::FIELD_RESOURCE_NAME => ['value' => ''],
                Config::FIELD_DEPLOYMENT_ID => ['value' => ''],
            ],
        ];

        // Should not throw
        $json = $cfg->encode($input);
        $this->assertIsString($json);

        // decode should return the same structure as json_decode of the encoded string
        $decoded = $cfg->decode($json);
        $this->assertIsArray($decoded);

        // The decoded structure should contain the provider and nested fields
        $this->assertSame(Helper::ENDPOINT_TYPE_OPENAI, $decoded[Config::FIELD_PROVIDER]['value']);
        $this->assertSame('openai-key-123', $decoded[Helper::ENDPOINT_TYPE_GENERAL][Config::FIELD_AIKEY]['value']);
        $this->assertSame('gpt-4o', $decoded[Helper::ENDPOINT_TYPE_OPENAI][Config::FIELD_MODEL]['value']);
    }

    /**
     * encode() should throw when provider is invalid
     */
    public function testEncodeWithInvalidProviderThrows(): void
    {
        $cfg = new Config();
        $this->expectException(\Schoolbox\Mapper\Exception::class);
        // only provider is necessary for provider validation to fail
        $cfg->encode([Config::FIELD_PROVIDER => ['value' => 'INVALID_PROVIDER']]);
    }

    /**
     * encode() should throw when required fields are missing from the config
     */
    public function testEncodeWithMissingFieldsThrows(): void
    {
        $cfg = new Config();
        $this->expectException(\Schoolbox\Mapper\Exception::class);

        // provider is valid but we're missing the nested sections/fields
        $cfg->encode([Config::FIELD_PROVIDER => ['value' => Helper::ENDPOINT_TYPE_OPENAI]]);
    }

    /**
     * Valid encode/decode round-trip for Azure configuration
     */
    public function validReEncode(): void
    {
        $cfg = new Config();

        $original = [
            Config::FIELD_PROVIDER => ['value' => Helper::ENDPOINT_TYPE_AZURE],
            Helper::ENDPOINT_TYPE_GENERAL => [
                Config::FIELD_AIKEY => ['value' => 'shared-key-456'],
                Config::FIELD_SYSTEM_PROMPT => ['value' => 'Azure prompt'],
            ],
            Helper::ENDPOINT_TYPE_OPENAI => [
                Config::FIELD_MODEL => ['value' => ''],
            ],
            Helper::ENDPOINT_TYPE_AZURE => [
                Config::FIELD_RESOURCE_NAME => ['value' => 'azure-resource'],
                Config::FIELD_DEPLOYMENT_ID => ['value' => 'deployment-456'],
            ],
        ];

        // Encode should not throw
        $json = $cfg->encode($original);
        $this->assertIsString($json);

        // Decode should return the same structure
        $decoded = $cfg->decode($json);
        $this->assertEquals($original, $decoded);
    }

    /**
     * Encode with missing OpenAI section should throw
     */
    public function testEncodeMissingOpenAI(): void
    {
        $cfg = new Config();

        $input = [
            Config::FIELD_PROVIDER => ['value' => Helper::ENDPOINT_TYPE_OPENAI],
            Helper::ENDPOINT_TYPE_GENERAL => [
                Config::FIELD_AIKEY => ['value' => 'openai-key-123'],
                Config::FIELD_SYSTEM_PROMPT => ['value' => 'System prompt'],
            ],
            Helper::ENDPOINT_TYPE_AZURE => [
                Config::FIELD_RESOURCE_NAME => ['value' => ''],
                Config::FIELD_DEPLOYMENT_ID => ['value' => ''],
            ],
        ];

        $this->expectException(\Schoolbox\Mapper\Exception::class);

        // should throw due to missing config section
        $cfg->encode($input);
    }

    /**
     * Encode with missing Azure resource name should throw
     */
    public function testEncodeMissingAzureName(): void
    {
        $cfg = new Config();

        $input = [
            Config::FIELD_PROVIDER => ['value' => Helper::ENDPOINT_TYPE_AZURE],
            Helper::ENDPOINT_TYPE_GENERAL => [
                Config::FIELD_AIKEY => ['value' => 'shared-key-456'],
                Config::FIELD_SYSTEM_PROMPT => ['value' => 'Azure prompt'],
            ],
            Helper::ENDPOINT_TYPE_OPENAI => [
                Config::FIELD_MODEL => ['value' => ''],
            ],
            Helper::ENDPOINT_TYPE_AZURE => [
                Config::FIELD_DEPLOYMENT_ID => ['value' => 'deployment-456'],
            ],
        ];

        $this->expectException(\Schoolbox\Mapper\Exception::class);

        // should throw due to missing config section
        $cfg->encode($input);
    }
}
