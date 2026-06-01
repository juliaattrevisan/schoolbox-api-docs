<?php

/*
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\OpenAI\Test;

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
     * Data provider for isEnabled
     *
     * @return array
     */
    public static function providerIsEnabled(): array
    {
        return [
            'OpenAI enabled' => [
                [Config::FIELD_PROVIDER => ['value' => Helper::ENDPOINT_TYPE_OPENAI]],
                true,
            ],
            'Azure enabled' => [
                [Config::FIELD_PROVIDER => ['value' => Helper::ENDPOINT_TYPE_AZURE]],
                true,
            ],
            'Provider off' => [
                [Config::FIELD_PROVIDER => ['value' => Helper::ENDPOINT_TYPE_OFF]],
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
     * Test that isEnabled throws error when provider is not an array
     */
    public function testIsEnabledWithDirectProviderValue(): void
    {
        $cfg = new Config();

        $this->expectException(TypeError::class);
        $this->assertFalse($cfg->isEnabled([Config::FIELD_PROVIDER => Helper::ENDPOINT_TYPE_OPENAI]));
    }

    // test isEnabled with null config
    public function testIsEnabledWithNullReturnsFalse(): void
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
                Config::FIELD_RESOURCE_NAME    => ['value' => ''],
                Config::FIELD_DEPLOYMENT_ID    => ['value' => ''],
                Config::FIELD_DEPLOYMENT_MODEL => ['value' => ''],
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
        // JSON boolean true (valid JSON) decodes to boolean true which violates the ?array return type
        $this->expectException(TypeError::class);
        $cfg->decode('true');
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
     */
    public function testEncodeDecodeRoundTripOpenAI(): void
    {
        $cfg = new Config();

        $input = [
            Config::FIELD_PROVIDER => ['value' => Helper::ENDPOINT_TYPE_OPENAI],
            Helper::ENDPOINT_TYPE_GENERAL => [
                Config::FIELD_AIKEY => ['value' => 'openai-key-123'],
                Config::FIELD_SYSTEM_PROMPT => ['value' => 'System prompt'],
                Config::FIELD_AI_MODERATION => ['value' => '1'],
            ],
            Helper::ENDPOINT_TYPE_OPENAI => [
                Config::FIELD_MODEL => ['value' => 'gpt-4o'],
            ],
            Helper::ENDPOINT_TYPE_AZURE => [
                Config::FIELD_RESOURCE_NAME    => ['value' => ''],
                Config::FIELD_DEPLOYMENT_ID    => ['value' => ''],
                Config::FIELD_DEPLOYMENT_MODEL => ['value' => ''],
            ],
        ];

        // Should not throw
        $json = $cfg->encode($input, Helper::FEATURE_CHAT);
        $this->assertIsString($json);

        // decode should return an array
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
        // provider validation happens before structure checks - provider must be nested ['value'=>...]
        $cfg->encode([Config::FIELD_PROVIDER => ['value' => 'INVALID_PROVIDER']], Helper::FEATURE_CHAT);
    }

    /**
     * encode() should throw when required top-level sections are missing
     */
    public function testEncodeWithMissingTopLevelSectionsThrows(): void
    {
        $cfg = new Config();
        $this->expectException(\Schoolbox\Mapper\Exception::class);

        // missing the Azure top-level key -> should trigger invalid structure
        $cfg->encode(
            [
                Config::FIELD_PROVIDER => ['value' => Helper::ENDPOINT_TYPE_OPENAI],
                Helper::ENDPOINT_TYPE_GENERAL => [
                    Config::FIELD_AIKEY => ['value' => 'openai-key-123'],
                    Config::FIELD_SYSTEM_PROMPT => ['value' => 'System prompt'],
                    Config::FIELD_AI_MODERATION => ['value' => '1'],
                ],
                Helper::ENDPOINT_TYPE_OPENAI => [
                    Config::FIELD_MODEL => ['value' => 'gpt-4o'],
                ],
                // NOTE: Helper::ENDPOINT_TYPE_AZURE is intentionally omitted
            ],
            Helper::FEATURE_CHAT,
        );
    }

    /**
     * Valid encode/decode round-trip for Azure configuration
     */
    public function testValidReEncode(): void
    {
        $cfg = new Config();

        $input = [
            Config::FIELD_PROVIDER => ['value' => Helper::ENDPOINT_TYPE_AZURE],
            Helper::ENDPOINT_TYPE_GENERAL => [
                Config::FIELD_AIKEY => ['value' => 'shared-key-456'],
                Config::FIELD_SYSTEM_PROMPT => ['value' => 'Azure prompt'],
                Config::FIELD_AI_MODERATION => ['value' => '1'],
            ],
            Helper::ENDPOINT_TYPE_OPENAI => [
                Config::FIELD_MODEL => ['value' => ''],
            ],
            Helper::ENDPOINT_TYPE_AZURE => [
                Config::FIELD_RESOURCE_NAME    => ['value' => 'azure-resource'],
                Config::FIELD_DEPLOYMENT_ID    => ['value' => 'deployment-456'],
                Config::FIELD_DEPLOYMENT_MODEL => ['value' => 'gpt-5.5'],
            ],
        ];

        $json = $cfg->encode($input, Helper::FEATURE_CHAT);
        $this->assertIsString($json);

        $decoded = $cfg->decode($json);
        $this->assertIsArray($decoded);
        $this->assertSame(Helper::ENDPOINT_TYPE_AZURE, $decoded[Config::FIELD_PROVIDER]['value']);
        $this->assertSame('azure-resource', $decoded[Helper::ENDPOINT_TYPE_AZURE][Config::FIELD_RESOURCE_NAME]['value']);
        $this->assertSame('deployment-456', $decoded[Helper::ENDPOINT_TYPE_AZURE][Config::FIELD_DEPLOYMENT_ID]['value']);
    }

    /**
     * Encode with missing OpenAI section should throw (top-level omission)
     */
    public function testEncodeMissingOpenAI(): void
    {
        $cfg = new Config();

        $input = [
            Config::FIELD_PROVIDER => ['value' => Helper::ENDPOINT_TYPE_OPENAI],
            Helper::ENDPOINT_TYPE_GENERAL => [
                Config::FIELD_AIKEY => ['value' => 'openai-key-123'],
                Config::FIELD_SYSTEM_PROMPT => ['value' => 'System prompt'],
                Config::FIELD_AI_MODERATION => ['value' => '1'],
            ],
            // Helper::ENDPOINT_TYPE_OPENAI is intentionally omitted
            Helper::ENDPOINT_TYPE_AZURE => [
                Config::FIELD_RESOURCE_NAME    => ['value' => ''],
                Config::FIELD_DEPLOYMENT_ID    => ['value' => ''],
                Config::FIELD_DEPLOYMENT_MODEL => ['value' => ''],
            ],
        ];

        $this->expectException(\Schoolbox\Mapper\Exception::class);

        // should throw due to missing top-level OpenAI section
        $cfg->encode($input, Helper::FEATURE_CHAT);
    }

    /**
     * Encode with extra Azure nested fields should throw according to current implementation
     */
    public function testEncodeWithExtraAzureNestedFieldsThrows(): void
    {
        $cfg = new Config();

        $input = [
            Config::FIELD_PROVIDER => ['value' => Helper::ENDPOINT_TYPE_AZURE],
            Helper::ENDPOINT_TYPE_GENERAL => [
                Config::FIELD_AIKEY => ['value' => 'shared-key-456'],
                Config::FIELD_SYSTEM_PROMPT => ['value' => 'Azure prompt'],
                Config::FIELD_AI_MODERATION => ['value' => '1'],
            ],
            Helper::ENDPOINT_TYPE_OPENAI => [
                Config::FIELD_MODEL => ['value' => ''],
            ],
            Helper::ENDPOINT_TYPE_AZURE => [
                Config::FIELD_RESOURCE_NAME    => ['value' => ''],
                Config::FIELD_DEPLOYMENT_ID    => ['value' => ''],
                Config::FIELD_DEPLOYMENT_MODEL => ['value' => ''],
                'extra_field'                  => ['value' => 'should not be here']
            ],
        ];

        $this->expectException(\Schoolbox\Mapper\Exception::class);

        // should throw due to extra nested field
        $cfg->encode($input, Helper::FEATURE_CHAT);
    }
}
