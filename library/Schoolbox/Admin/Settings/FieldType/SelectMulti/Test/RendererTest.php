<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Settings\FieldType\SelectMulti\Test;

use PHPUnit\Framework as PHPUnitFramework;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Schoolbox\Admin\Settings\Exceptions\FieldTypeMismatchException;
use Schoolbox\Admin\Settings\FieldStrategyFactory;
use Schoolbox\Admin\Settings\FieldType;
use Schoolbox\Config\Config;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\MapperFactory;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use SebastianBergmann\RecursionContext;

/**
 * @codeCoverageIgnore
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\Admin\Settings\FieldType\SelectMulti\Renderer::class)]
class RendererTest extends TestCase
{

    // Tests
    /**
     * Test the renderer provides expected data structure for the twig template
     *
     * @param Config $config
     * @throws PHPUnitFramework\Exception
     * @throws FieldTypeMismatchException
     * @throws RecursionContext\InvalidArgumentException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerConfig')]
    public function testTemplateData(Config $config): void
    {
        $renderer = $this->buildFieldStrategyFactory()->makeRenderer(ConfigMapper::FIELD_SELECT_MULTI);
        $actual   = $renderer->getTemplateAndData($config);
        self::assertArrayHasKey('data', $actual);
        self::assertArrayHasKey('name', $actual['data']);
        self::assertArrayHasKey('value', $actual['data']);
        self::assertArrayHasKey('options', $actual['data']);
        self::assertArrayHasKey('required', $actual['data']);
        self::assertArrayHasKey('renderEmptyOption', $actual['data']);
        self::assertArrayHasKey('multi', $actual['data']);
    }


    /**
     * Test the renderer provides expected data structure
     *
     * @param Config $config
     * @param int    $invalidFieldId
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
     * @param FieldType\SelectMulti\Renderer $renderer
     * @param Config                         $config
     * @param string|null                    $value
     * @param string|null                    $expected
     * @throws FieldTypeMismatchException
     * @throws PHPUnitFramework\ExpectationFailedException
     * @throws RecursionContext\InvalidArgumentException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerTransformValue')]
    public function testTransformValue(FieldType\SelectMulti\Renderer $renderer, Config $config, ?string $value, ?string $expected): void
    {
        $config->setValue($value);
        self::assertSame($expected, $renderer->getTemplateAndData($config)['data']['value']);
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
     */
    public function providerTransformValue(): array
    {
        $renderer = $this->buildFieldStrategyFactory()->makeRenderer(ConfigMapper::FIELD_SELECT_MULTI);
        $config   = $this->buildConfig();

        // FieldType\SelectMulti\Renderer $renderer, Config $config, ?string $value, ?string $expected
        return [
            "null"           => [$renderer, $config, null, ''],
            "empty ''"       => [$renderer, $config, '', ''],
            "valid single"   => [$renderer, $config, '00', '00'],
            "valid multi"    => [$renderer, $config, '00,01', '00,01'],
            "invalid single" => [$renderer, $config, '2', ''],
            "invalid multi"  => [$renderer, $config, '00,2', '00'],

        ];
    }


    // Helpers


    /**
     * @return FieldStrategyFactory
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
            ->setName('schooldata_import_managed_hours')
            ->setValue('')
            ->setTitle('Schoolbox Managed hours')
            ->setDescription('Hours to run the import if "Import Frequency" = "Schoolbox Managed".')
            ->setFieldId(ConfigMapper::FIELD_SELECT_MULTI)
            ->setOptions('00:01:02:03:04:05:06:07:08:09:10:11:12:13:14:15:16:17:18:19:20:21:22:23')
            ->setEditable(true)
            ->setHidden(false)
            ->setRequired(true)
            ->setModule(ConfigMapper::MODULE_SCHOOLDATA);
    }
}
