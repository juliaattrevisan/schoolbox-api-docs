<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Settings\FieldType\Select\Test;

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
#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\Admin\Settings\FieldType\Select\Renderer::class)]
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
        $renderer = $this->buildFieldStrategyFactory()->makeRenderer(ConfigMapper::FIELD_SELECT);
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
        $renderer = $this->buildFieldStrategyFactory()->makeRenderer(ConfigMapper::FIELD_SELECT);
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
        $renderer = $this->buildFieldStrategyFactory()->makeRenderer(ConfigMapper::FIELD_SELECT);
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
            'selectKey'    => [self::buildConfig(), ConfigMapper::FIELD_SELECT_KEY],
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
            "null"            => [$config, null, null],
            "empty ''"        => [$config, '', null],
            "'0'"             => [$config, '0', null],
            "'1'"             => [$config, '1', null],
            "'2'"             => [$config, '2', null],
            "valid 'digest'"  => [$config, 'digest', 'digest'],
            "valid 'basic'"   => [$config, 'basic', 'basic'],
            "unexploded"      => [$config, 'digest:basic', null],
            "'invalid value'" => [$config, 'invalid value', null],
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
            "optional, null"            => [$config, false, null, true],
            "optional, ''"              => [$config, false, '', true],
            "optional, '0'"             => [$config, false, '0', true],
            "optional, '1'"             => [$config, false, '1', true],
            "optional, '2'"             => [$config, false, '2', true],
            "optional, 'digest'"        => [$config, false, 'digest', true],
            "optional, 'invalid value'" => [$config, false, 'invalid value', true],
            "required, null"            => [$config, true, null, true],
            "required, ''"              => [$config, true, '', true],
            "required, '0'"             => [$config, true, '0', true],
            "required, '1'"             => [$config, true, '1', true],
            "required, '2'"             => [$config, true, '2', true],
            "required, 'digest'"        => [$config, true, 'digest', false],
            "required, 'invalid value'" => [$config, true, 'invalid value', true],
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
     * Returns a standard Select config
     *
     * @return Config
     */
    protected static function buildConfig(): Config
    {
        return (new Config())
            ->setId(236)
            ->setName('webdav_auth_type')
            ->setValue('digest')
            ->setTitle('WebDAV authentication type')
            ->setDescription('Select a WebDAV authentication type. You should use "Digest" unless your school uses SAML, in which case you must use "Basic".')
            ->setFieldId(ConfigMapper::FIELD_SELECT)
            ->setOptions('digest:basic')
            ->setTypeId(ConfigMapper::TYPE_RESOURCES)
            ->setEditable(true)
            ->setHidden(false)
            ->setRequired(false)
            ->setModule(ConfigMapper::MODULE_SETTINGS);
    }
}
