<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Settings\FieldType\SelectKey\Test;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Schoolbox\Admin\Settings\Exceptions\FieldTypeMismatchException;
use Schoolbox\Admin\Settings\FieldStrategyFactory;
use Schoolbox\Config\Config;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\MapperFactory;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;

/**
 * @codeCoverageIgnore
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\Admin\Settings\FieldType\SelectKey\Renderer::class)]
class RendererTest extends TestCase
{
    // Tests
    /**
     * Test the renderer provides expected data structure for the twig template
     *
     * @param Config $config
     * @throws \PHPUnit\Framework\Exception
     * @throws FieldTypeMismatchException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerConfig')]
    public function testTemplateData(Config $config): void
    {
        $renderer = $this->buildFieldStrategyFactory()->makeRenderer(ConfigMapper::FIELD_SELECT_KEY);
        $actual   = $renderer->getTemplateAndData($config);
        $this->assertArrayHasKey('template', $actual);
        $this->assertArrayHasKey('data', $actual);
        $this->assertArrayHasKey('name', $actual['data']);
        $this->assertArrayHasKey('value', $actual['data']);
        $this->assertArrayHasKey('options', $actual['data']);
        $this->assertArrayHasKey('required', $actual['data']);
        $this->assertArrayHasKey('renderEmptyOption', $actual['data']);
    }


    /**
     * Test the renderer provides expected data structure
     *
     * @param Config $config
     * @param int    $invalidFieldId
     * @throws \PHPUnit\Framework\Exception
     * @throws FieldTypeMismatchException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerConfigAndInvalidFieldId')]
    public function testInvalidField(Config $config, int $invalidFieldId): void
    {
        $this->expectException(FieldTypeMismatchException::class);
        $renderer = $this->buildFieldStrategyFactory()->makeRenderer($invalidFieldId);
        $renderer->getTemplateAndData($config);
    }


    /**
     * Tests that the renderer converts an invalid value to NULL
     *
     * An invalid value is one that doesn't match one of the available options
     *
     * @param Config      $config
     * @param string|null $value
     * @param string|null $expected
     * @throws FieldTypeMismatchException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerTransformValue')]
    public function testTransformValue(Config $config, ?string $value, ?string $expected): void
    {
        $renderer = $this->buildFieldStrategyFactory()->makeRenderer(ConfigMapper::FIELD_SELECT_KEY);
        $config->setValue($value);
        $this->assertSame($expected, $renderer->getTemplateAndData($config)['data']['value']);
    }


    /**
     * Tests whether for a combination of $required and $value, the system should insert an empty select option
     *
     * @param Config      $config
     * @param bool        $required
     * @param string|null $value
     * @param bool        $expected
     * @throws FieldTypeMismatchException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerAddEmptyOption')]
    public function testAddEmptyOption(Config $config, bool $required, ?string $value, bool $expected): void
    {
        $renderer = $this->buildFieldStrategyFactory()->makeRenderer(ConfigMapper::FIELD_SELECT_KEY);
        $config
            ->setRequired($required)
            ->setValue($value);
        $this->assertSame($expected, $renderer->getTemplateAndData($config)['data']['renderEmptyOption']);
    }

    // Providers

    /**
     * @return array
     */
    public static function providerConfig(): array
    {
        return [
            'normal config' => [self::buildConfig()],
        ];
    }


    /**
     * @return array
     */
    public static function providerConfigAndInvalidFieldId(): array
    {
        return [
            'boolean'      => [self::buildConfig(), ConfigMapper::FIELD_BOOLEAN],
            'selectKey'    => [self::buildConfig(), ConfigMapper::FIELD_SELECT],
            'selectLookup' => [self::buildConfig(), ConfigMapper::FIELD_SELECT_LOOKUP],
        ];
    }


    /**
     * @return array
     * @throws \PHPUnit\Framework\Exception
     */
    public static function providerTransformValue(): array
    {
        $config = self::buildConfig();

        return [
            "null"                 => [$config, null, null],
            "empty ''"             => [$config, '', null],
            "valid '0'"            => [$config, '0', '0'],
            "valid '1'"            => [$config, '1', '1'],
            "valid '2'"            => [$config, '2', '2'],
            "value instead of key" => [$config, 'Everyone', null],
            "unexploded"           => [$config, '0;Everyone:1', null],
            "'invalid value'"      => [$config, 'invalid value', null],
        ];
    }


    /**
     * @return array
     * @throws \PHPUnit\Framework\Exception
     */
    public static function providerAddEmptyOption(): array
    {
        $config = self::buildConfig();

        return [
            "optional, null"                 => [$config, false, null, true],
            "optional, empty string "        => [$config, false, '', true],
            "optional, valid value"          => [$config, false, '0', true],
            "optional, invalid int value"    => [$config, false, '9', true],
            "optional, invalid string value" => [$config, false, 'invalid value', true],
            "required, null"                 => [$config, true, null, true],
            "required, empty string "        => [$config, true, '', true],
            "required, valid value"          => [$config, true, '0', false],
            "required, invalid int value"    => [$config, true, '9', true],
            "required, invalid string value" => [$config, true, 'invalid value', true],
        ];
    }

    // Helpers

    /**
     * @throws \PHPUnit\Framework\Exception
     */
    protected function buildFieldStrategyFactory(): FieldStrategyFactory
    {
        /** @var MapperFactory|MockObject $mockMapperFactory */
        $mockMapperFactory = $this->createMock(MapperFactory::class);
        return new FieldStrategyFactory($mockMapperFactory, new CollectorFactory());
    }


    /**
     * Returns a standard SelectKey config
     *
     * @return Config
     */
    protected static function buildConfig(): Config
    {
        return (new Config())
            ->setId(71)
            ->setName('protect_teacher_photo')
            ->setValue('0')
            ->setTitle('Teacher Photo Visibility')
            ->setDescription('Select who can see the staff profile photos')
            ->setFieldId(ConfigMapper::FIELD_SELECT_KEY)
            ->setOptions('0;Everyone:1;Staff Only:2;Staff and Parents')
            ->setTypeId(ConfigMapper::TYPE_POLICY)
            ->setEditable(true)
            ->setHidden(false)
            ->setRequired(true)
            ->setModule(ConfigMapper::MODULE_SETTINGS);
    }
}
