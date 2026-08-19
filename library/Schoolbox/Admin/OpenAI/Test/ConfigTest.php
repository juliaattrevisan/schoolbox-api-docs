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
use Schoolbox\Config\ArrayLoader;
use Schoolbox\Config\Config as ConfigModel;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Editor\OpenAI\Helper;
use Schoolbox\OpenAi\Feature;
use TypeError;

/**
 * Class ConfigTest
 *
 * Tests for Schoolbox\Admin\OpenAI\Config
 */
class ConfigTest extends TestCase
{
    /**
     * @return void
     */
    public function testFetchFeaturesReturnsEmptyArrayWhenNoAiFeaturesAreConfigured(): void
    {
        $configMapper = $this->createMock(ConfigMapper::class);
        $configMapper->expects($this->once())
                     ->method('findByColumn')
                     ->willReturn([]);

        $this->assertSame([], (new Config())->fetchFeatures($configMapper, new ArrayLoader()));
    }

    /**
     * The rubric generation card remains configuration-only, but uses the same
     * provider fields and model options as Learning Activity Customisation.
     */
    public function testRubricGenerationConfigurationMatchesLearningActivityCustomisation(): void
    {
        $config = new Config();

        $this->assertSame(
            ConfigMapper::NAME_AI_RUBRIC_GENERATION,
            Config::ALL_MODULES[Feature::RUBRIC_GENERATION->value],
        );
        $this->assertSame(
            Config::RECOMMENDED_MODELS[Feature::LEARNING_ACTIVITY_CUSTOMISATION->value],
            Config::RECOMMENDED_MODELS[Feature::RUBRIC_GENERATION->value],
        );
        $this->assertSame(
            $config->initGeneral(Feature::LEARNING_ACTIVITY_CUSTOMISATION->value),
            $config->initGeneral(Feature::RUBRIC_GENERATION->value),
        );
        $this->assertSame(
            $config->initOpenAi(Feature::LEARNING_ACTIVITY_CUSTOMISATION->value),
            $config->initOpenAi(Feature::RUBRIC_GENERATION->value),
        );
        $this->assertSame(
            $config->initAzure(Feature::LEARNING_ACTIVITY_CUSTOMISATION->value),
            $config->initAzure(Feature::RUBRIC_GENERATION->value),
        );
    }

    /**
     * @return array<string, array{bool, bool}>
     */
    public static function fetchFeaturesPlanVisibilityProvider(): array
    {
        return [
            'excludes disabled plan feature' => [false, false],
            'includes disabled plan feature for history' => [true, true],
        ];
    }

    /**
     * @param bool $includeDisabledPlanFeatures
     * @param bool $containsFeature
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('fetchFeaturesPlanVisibilityProvider')]
    public function testFetchFeaturesPlanVisibility(bool $includeDisabledPlanFeatures, bool $containsFeature): void
    {
        $featureConfig = (new ConfigModel())
            ->setName(ConfigMapper::NAME_AI_TEXT_CHAT)
            ->setTitle('AI Text Chat')
            ->setValue(json_encode([
                                       Config::FIELD_PROVIDER => ['value' => Helper::ENDPOINT_TYPE_OFF],
                                   ], JSON_THROW_ON_ERROR));
        $configMapper = $this->createMock(ConfigMapper::class);
        $configMapper->expects($this->once())
                     ->method('findByColumn')
                     ->willReturn([$featureConfig]);

        $features = (new Config())->fetchFeatures(
            $configMapper,
            new ArrayLoader([ConfigMapper::NAME_AI_TEXT_CHAT_ENABLED => 'false']),
            $includeDisabledPlanFeatures,
        );

        $this->assertSame($containsFeature, isset($features['chat']));
    }

    /**
     * Plan-disabled features retain explicit availability metadata for browser consumers.
     *
     * @return void
     */
    public function testFetchFeaturesIncludesPlanAvailabilityMetadata(): void
    {
        $featureConfig = (new ConfigModel())
            ->setName(ConfigMapper::NAME_AI_TEXT_CHAT)
            ->setTitle('AI Text Chat')
            ->setValue(json_encode([
                                       Config::FIELD_PROVIDER => ['value' => Helper::ENDPOINT_TYPE_OPENAI],
                                   ], JSON_THROW_ON_ERROR));
        $configMapper = $this->createMock(ConfigMapper::class);
        $configMapper->expects($this->once())
                     ->method('findByColumn')
                     ->willReturn([$featureConfig]);

        $features = (new Config())->fetchFeatures(
            $configMapper,
            new ArrayLoader([ConfigMapper::NAME_AI_TEXT_CHAT_ENABLED => 'false']),
            true,
        );

        $this->assertFalse($features['chat']['planEnabled']);
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
     * The combined predicate must require both plan and provider availability.
     *
     * @return void
     */
    public function testIsFeatureEnabledRequiresPlanAndProvider(): void
    {
        $cfg = new Config();
        $config = [Config::FIELD_PROVIDER => ['value' => Helper::ENDPOINT_TYPE_OPENAI]];

        $this->assertTrue($cfg->isFeatureEnabled(
            $config,
            ConfigMapper::NAME_AI_TEXT_CHAT,
            new ArrayLoader([ConfigMapper::NAME_AI_TEXT_CHAT_ENABLED => 'true']),
        ));
        $this->assertFalse($cfg->isFeatureEnabled(
            $config,
            ConfigMapper::NAME_AI_TEXT_CHAT,
            new ArrayLoader([ConfigMapper::NAME_AI_TEXT_CHAT_ENABLED => 'false']),
        ));
        $this->assertFalse($cfg->isFeatureEnabled(
            [Config::FIELD_PROVIDER => ['value' => Helper::ENDPOINT_TYPE_OFF]],
            ConfigMapper::NAME_AI_TEXT_CHAT,
            new ArrayLoader([ConfigMapper::NAME_AI_TEXT_CHAT_ENABLED => 'true']),
        ));
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
        $json = $cfg->encode($input, \Schoolbox\OpenAi\Feature::CHAT->value);
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
        $cfg->encode([Config::FIELD_PROVIDER => ['value' => 'INVALID_PROVIDER']], \Schoolbox\OpenAi\Feature::CHAT->value);
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
            \Schoolbox\OpenAi\Feature::CHAT->value,
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

        $json = $cfg->encode($input, \Schoolbox\OpenAi\Feature::CHAT->value);
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
        $cfg->encode($input, \Schoolbox\OpenAi\Feature::CHAT->value);
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
        $cfg->encode($input, \Schoolbox\OpenAi\Feature::CHAT->value);
    }

    /**
     * Call the private `splitOptions`.
     *
     * Nothing public exposes what it returns: `initFields()` reduces every form
     * input to `['value' => '']`, and the four lists it builds are read only by
     * `templates/formInput.twig`.
     *
     * @param array<string>          $options
     * @param array<string>          $recommendations
     * @param ?array<string, string> $deprecations
     * @param ?array<string, string> $shutdowns
     * @return array
     */
    private function splitOptions(
        array $options,
        array $recommendations,
        ?array $deprecations,
        ?array $shutdowns,
    ): array {
        return (new \ReflectionMethod(Config::class, 'splitOptions'))
            ->invoke(new Config(), $options, $recommendations, $deprecations, $shutdowns);
    }

    /**
     * Data provider for splitOptions
     *
     * @return array
     */
    public static function providerSplitOptions(): array
    {
        return [
            /* The ordinary case: every model is accounted for exactly once, and
             * a deprecated or shut down model is held back from `others` so the
             * form does not offer it.
             */
            'each model lands in exactly one list' => [
                ['gpt-5', 'gpt-4o', 'gpt-4', 'gpt-3.5'],
                ['gpt-5'],
                ['gpt-4' => 'gpt-5'],
                ['gpt-3.5' => 'gpt-5'],
                [
                    'recommended'  => ['gpt-5'],
                    'others'       => ['gpt-4o'],
                    'deprecations' => ['gpt-4' => 'gpt-5'],
                    'shutdowns'    => ['gpt-3.5' => 'gpt-5'],
                ],
            ],
            /* Azure deployment models are split with no deprecation or shutdown
             * list at all, so null must behave as "none of them".
             */
            'null deprecations and shutdowns leave every unrecommended model in others' => [
                ['gpt-5', 'gpt-4o', 'gpt-4'],
                ['gpt-5'],
                null,
                null,
                [
                    'recommended'  => ['gpt-5'],
                    'others'       => ['gpt-4o', 'gpt-4'],
                    'deprecations' => [],
                    'shutdowns'    => [],
                ],
            ],
            /* The deprecation and shutdown lists are global, so they name models
             * that a given field does not offer. Those must not be reported for
             * a field whose options do not include them.
             */
            'a deprecation for a model this field does not offer is dropped' => [
                ['gpt-image-1'],
                [],
                ['gpt-4' => 'gpt-5'],
                ['gpt-3.5' => 'gpt-5'],
                [
                    'recommended'  => [],
                    'others'       => ['gpt-image-1'],
                    'deprecations' => [],
                    'shutdowns'    => [],
                ],
            ],
            'no recommendations leaves every model in others' => [
                ['gpt-5', 'gpt-4o'],
                [],
                null,
                null,
                [
                    'recommended'  => [],
                    'others'       => ['gpt-5', 'gpt-4o'],
                    'deprecations' => [],
                    'shutdowns'    => [],
                ],
            ],
            'no options produces four empty lists' => [
                [],
                [],
                ['gpt-4' => 'gpt-5'],
                ['gpt-3.5' => 'gpt-5'],
                [
                    'recommended'  => [],
                    'others'       => [],
                    'deprecations' => [],
                    'shutdowns'    => [],
                ],
            ],
            /* A model can be deprecated in favour of more than one successor,
             * and the replacement text is carried through untouched.
             */
            'the replacement named by a deprecation is carried through' => [
                ['gpt-4', 'gpt-4o'],
                [],
                ['gpt-4' => 'gpt-5 or gpt-5-mini'],
                null,
                [
                    'recommended'  => [],
                    'others'       => ['gpt-4o'],
                    'deprecations' => ['gpt-4' => 'gpt-5 or gpt-5-mini'],
                    'shutdowns'    => [],
                ],
            ],
        ];
    }

    /**
     * @param array<string>          $options
     * @param array<string>          $recommendations
     * @param ?array<string, string> $deprecations
     * @param ?array<string, string> $shutdowns
     * @param array                  $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerSplitOptions')]
    public function testSplitOptions(
        array $options,
        array $recommendations,
        ?array $deprecations,
        ?array $shutdowns,
        array $expected,
    ): void {
        $this->assertSame(
            $expected,
            $this->splitOptions($options, $recommendations, $deprecations, $shutdowns),
        );
    }

    /**
     * `others` is built with `array_diff`, which keeps the keys of the array it
     * filters, so the models that survive the filter are left with the gaps of
     * the ones that did not.
     *
     * The keys are reindexed to close those gaps, which keeps `others` a list:
     * `json_encode` renders a gapped list as an object, so without this a caller
     * sending these options to the browser as JSON gets an object of models
     * keyed by their position among all models.
     */
    public function testSplitOptionsClosesTheGapsInTheKeysOfOthers(): void
    {
        $split = $this->splitOptions(['gpt-5', 'gpt-4o', 'gpt-4'], ['gpt-4o'], null, null);

        $this->assertSame([0 => 'gpt-5', 1 => 'gpt-4'], $split['others']);
        $this->assertSame('["gpt-5","gpt-4"]', json_encode($split['others'], JSON_THROW_ON_ERROR));
    }

    /**
     * Data provider for splitOptions precedence
     *
     * A model can appear in more than one of the lists given to `splitOptions`.
     * Shutdown beats deprecation, which beats recommendation, so that whichever
     * list a model is reported in, it is reported in only that one and the form
     * offers it once.
     *
     * @return array
     */
    public static function providerSplitOptionsPrecedence(): array
    {
        return [
            'shutdown beats recommendation' => [
                ['gpt-3.5', 'gpt-5'],
                ['gpt-3.5'],
                null,
                ['gpt-3.5' => 'gpt-5'],
                [
                    'recommended'  => [],
                    'others'       => ['gpt-5'],
                    'deprecations' => [],
                    'shutdowns'    => ['gpt-3.5' => 'gpt-5'],
                ],
            ],
            'deprecation beats recommendation' => [
                ['gpt-4', 'gpt-5'],
                ['gpt-4'],
                ['gpt-4' => 'gpt-5'],
                null,
                [
                    'recommended'  => [],
                    'others'       => ['gpt-5'],
                    'deprecations' => ['gpt-4' => 'gpt-5'],
                    'shutdowns'    => [],
                ],
            ],
            'shutdown beats deprecation' => [
                ['gpt-3.5', 'gpt-5'],
                [],
                ['gpt-3.5' => 'gpt-4o'],
                ['gpt-3.5' => 'gpt-5'],
                [
                    'recommended'  => [],
                    'others'       => ['gpt-5'],
                    'deprecations' => [],
                    'shutdowns'    => ['gpt-3.5' => 'gpt-5'],
                ],
            ],
            'shutdown beats both deprecation and recommendation' => [
                ['gpt-3.5', 'gpt-5'],
                ['gpt-3.5'],
                ['gpt-3.5' => 'gpt-4o'],
                ['gpt-3.5' => 'gpt-5'],
                [
                    'recommended'  => [],
                    'others'       => ['gpt-5'],
                    'deprecations' => [],
                    'shutdowns'    => ['gpt-3.5' => 'gpt-5'],
                ],
            ],
            /* Only the model that is in more than one list loses its place: the
             * recommendations either side of it are untouched.
             */
            'the other recommendations survive' => [
                ['gpt-5', 'gpt-4', 'gpt-5-mini'],
                ['gpt-5', 'gpt-4', 'gpt-5-mini'],
                ['gpt-4' => 'gpt-5'],
                null,
                [
                    'recommended'  => ['gpt-5', 'gpt-5-mini'],
                    'others'       => [],
                    'deprecations' => ['gpt-4' => 'gpt-5'],
                    'shutdowns'    => [],
                ],
            ],
        ];
    }

    /**
     * @param array<string>          $options
     * @param array<string>          $recommendations
     * @param ?array<string, string> $deprecations
     * @param ?array<string, string> $shutdowns
     * @param array                  $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerSplitOptionsPrecedence')]
    public function testSplitOptionsPrecedence(
        array $options,
        array $recommendations,
        ?array $deprecations,
        ?array $shutdowns,
        array $expected,
    ): void {
        $this->assertSame(
            $expected,
            $this->splitOptions($options, $recommendations, $deprecations, $shutdowns),
        );
    }

    /**
     * `recommended` is returned exactly as given, so a recommendation the field
     * does not offer still reaches the form, where it renders as a selectable
     * option that is not one of this field's models.
     *
     * Pinned for the same reason as the test above.
     */
    public function testSplitOptionsReturnsARecommendationThatIsNotAnAvailableOption(): void
    {
        $split = $this->splitOptions(['gpt-4o'], ['a-model-this-field-does-not-offer'], null, null);

        $this->assertSame(['a-model-this-field-does-not-offer'], $split['recommended']);
        $this->assertSame(['gpt-4o'], $split['others']);
    }
}
