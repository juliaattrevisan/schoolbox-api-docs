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
use Schoolbox\Admin\Settings\FieldType;
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
     * @param FieldType\Select\Renderer $renderer
     * @param Config                    $config
     * @param string|null               $value
     * @param string|null               $expected
     * @throws FieldTypeMismatchException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerTransformValue')]
    public function testTransformValue(FieldType\Select\Renderer $renderer, Config $config, ?string $value, ?string $expected): void
    {
        $config->setValue($value);
        $this->assertSame($expected, $renderer->getTemplateAndData($config)['data']['value']);
    }


    /**
     * Tests whether for a combination of $required and $value, the system should insert an empty select option
     *
     * @param FieldType\Select\Renderer $renderer
     * @param Config                    $config
     * @param bool                      $required
     * @param string|null               $value
     * @param bool                      $expected
     * @throws FieldTypeMismatchException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerAddEmptyOption')]
    public function testAddEmptyOption(FieldType\Select\Renderer $renderer, Config $config, bool $required, ?string $value, bool $expected): void
    {
        $config
            ->setRequired($required)
            ->setValue($value);
        $this->assertSame($expected, $renderer->getTemplateAndData($config)['data']['renderEmptyOption']);
    }

    // Providers

    /**
     * @return array
     */
    public function providerConfig(): array
    {
        return [
            'normal config' => [$this->buildConfig()],
        ];
    }


    /**
     * @return array
     */
    public function providerConfigAndInvalidFieldId(): array
    {
        return [
            'boolean'      => [$this->buildConfig(), ConfigMapper::FIELD_BOOLEAN],
            'selectKey'    => [$this->buildConfig(), ConfigMapper::FIELD_SELECT],
            'selectLookup' => [$this->buildConfig(), ConfigMapper::FIELD_SELECT_LOOKUP],
        ];
    }


    /**
     * @return array
     * @throws \PHPUnit\Framework\Exception
     */
    public function providerTransformValue(): array
    {
        $renderer = $this->buildFieldStrategyFactory()->makeRenderer(ConfigMapper::FIELD_SELECT_KEY);
        $config   = $this->buildConfig();

        return [
            "null"                 => [$renderer, $config, null,            null],
            "empty ''"             => [$renderer, $config, '',              null],
            "valid '0'"            => [$renderer, $config, '0',             '0'],
            "valid '1'"            => [$renderer, $config, '1',             '1'],
            "valid '2'"            => [$renderer, $config, '2',             '2'],
            "value instead of key" => [$renderer, $config, 'Everyone',      null],
            "unexploded"           => [$renderer, $config, '0;Everyone:1',  null],
            "'invalid value'"      => [$renderer, $config, 'invalid value', null],
        ];
    }


    /**
     * @return array
     * @throws \PHPUnit\Framework\Exception
     */
    public function providerAddEmptyOption(): array
    {
        $renderer = $this->buildFieldStrategyFactory()->makeRenderer(ConfigMapper::FIELD_SELECT_KEY);
        $config   = $this->buildConfig();

        return [
            "optional, null"                => [$renderer, $config, false, null,            true],
            "optional, empty string "       => [$renderer, $config, false, '',              true],
            "optional, valid value"         => [$renderer, $config, false, '0',             true],
            "optional, invalid int value"   => [$renderer, $config, false, '9',             true],
            "optional, invalid string value" => [$renderer, $config, false, 'invalid value', true],
            "required, null"                => [$renderer, $config, true,  null,            true],
            "required, empty string "       => [$renderer, $config, true,  '',              true],
            "required, valid value"         => [$renderer, $config, true,  '0',             false],
            "required, invalid int value"   => [$renderer, $config, true,  '9',             true],
            "required, invalid string value" => [$renderer, $config, true,  'invalid value', true],
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
    protected function buildConfig(): Config
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
