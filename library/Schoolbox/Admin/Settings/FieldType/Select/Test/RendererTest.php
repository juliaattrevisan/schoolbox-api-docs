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
use Schoolbox\Admin\Settings\FieldType;
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
            'selectKey'    => [$this->buildConfig(), ConfigMapper::FIELD_SELECT_KEY],
            'selectLookup' => [$this->buildConfig(), ConfigMapper::FIELD_SELECT_LOOKUP],
        ];
    }


    /**
     * @return array
     * @throws \PHPUnit\Framework\Exception
     */
    public function providerTransformValue(): array
    {
        $renderer = $this->buildFieldStrategyFactory()->makeRenderer(ConfigMapper::FIELD_SELECT);
        $config   = $this->buildConfig();

        return [
            "null"            => [$renderer, $config, null,            null],
            "empty ''"        => [$renderer, $config, '',              null],
            "'0'"             => [$renderer, $config, '0',             null],
            "'1'"             => [$renderer, $config, '1',             null],
            "'2'"             => [$renderer, $config, '2',             null],
            "valid 'digest'"  => [$renderer, $config, 'digest',       'digest'],
            "valid 'basic'"   => [$renderer, $config, 'basic',        'basic'],
            "unexploded"      => [$renderer, $config, 'digest:basic',  null],
            "'invalid value'" => [$renderer, $config, 'invalid value', null],
        ];
    }


    /**
     * @return array
     * @throws \PHPUnit\Framework\Exception
     */
    public function providerAddEmptyOption(): array
    {
        $renderer = $this->buildFieldStrategyFactory()->makeRenderer(ConfigMapper::FIELD_SELECT);
        $config   = $this->buildConfig();

        return [
            "optional, null"            => [$renderer, $config, false, null,            true],
            "optional, ''"              => [$renderer, $config, false, '',              true],
            "optional, '0'"             => [$renderer, $config, false, '0',             true],
            "optional, '1'"             => [$renderer, $config, false, '1',             true],
            "optional, '2'"             => [$renderer, $config, false, '2',             true],
            "optional, 'digest'"        => [$renderer, $config, false, 'digest',        true],
            "optional, 'invalid value'" => [$renderer, $config, false, 'invalid value', true],
            "required, null"            => [$renderer, $config, true,  null,            true],
            "required, ''"              => [$renderer, $config, true,  '',              true],
            "required, '0'"             => [$renderer, $config, true,  '0',             true],
            "required, '1'"             => [$renderer, $config, true,  '1',             true],
            "required, '2'"             => [$renderer, $config, true,  '2',             true],
            "required, 'digest'"        => [$renderer, $config, true,  'digest',        false],
            "required, 'invalid value'" => [$renderer, $config, true,  'invalid value', true],
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
    protected function buildConfig(): Config
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
