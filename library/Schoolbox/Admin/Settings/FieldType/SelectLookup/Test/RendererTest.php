<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Settings\FieldType\SelectLookup\Test;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Schoolbox\Admin\Settings\Exceptions\FieldTypeMismatchException;
use Schoolbox\Admin\Settings\FieldStrategyFactory;
use Schoolbox\Config\Config;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\GenericCollection;
use Schoolbox\MapperFactory;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel;

/**
 * @codeCoverageIgnore
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\Admin\Settings\FieldType\SelectLookup\Renderer::class)]
class RendererTest extends TestCase
{
    // Tests
    /**
     * Test the renderer provides expected data structure for the twig template
     *
     * @param Config $config
     * @throws \PHPUnit\Framework\Exception
     * @throws FieldTypeMismatchException
     * @throws \UnexpectedValueException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerConfig')]
    public function testTemplateData(Config $config): void
    {
        $renderer = $this->buildFieldStrategyFactory()->makeRenderer(ConfigMapper::FIELD_SELECT_LOOKUP);
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
     * @throws \UnexpectedValueException
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
        $renderer = $this->buildFieldStrategyFactory()->makeRenderer(ConfigMapper::FIELD_SELECT_LOOKUP);
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
        $renderer = $this->buildFieldStrategyFactory()->makeRenderer(ConfigMapper::FIELD_SELECT_LOOKUP);
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
            'selectLookup' => [self::buildConfig(), ConfigMapper::FIELD_SELECT_KEY],
        ];
    }


    /**
     * @return array
     * @throws \PHPUnit\Framework\Exception
     * @throws \UnexpectedValueException
     */
    public static function providerTransformValue(): array
    {
        $config = self::buildConfig();

        return [
            "null"                 => [$config, null, null],
            "empty ''"             => [$config, '', null],
            "valid '1'"            => [$config, '1', '1'],
            "valid '2'"            => [$config, '2', '2'],
            "value instead of key" => [$config, 'Standard', null],
            "unexploded"           => [$config, 'Standard:1', null],
            "'invalid value'"      => [$config, 'invalid value', null],
        ];
    }


    /**
     * @return array
     * @throws \PHPUnit\Framework\Exception
     * @throws \UnexpectedValueException
     */
    public static function providerAddEmptyOption(): array
    {
        $config = self::buildConfig();

        return [
            "optional, null"                 => [$config, false, null, true],
            "optional, empty string "        => [$config, false, '', true],
            "optional, valid value"          => [$config, false, '1', true],
            "optional, invalid int value"    => [$config, false, '9', true],
            "optional, invalid string value" => [$config, false, 'Standard', true],
            "required, null"                 => [$config, true, null, true],
            "required, empty string "        => [$config, true, '', true],
            "required, valid value"          => [$config, true, '1', false],
            "required, invalid int value"    => [$config, true, '9', true],
            "required, invalid string value" => [$config, true, 'Standard', true],
        ];
    }

    // Helpers


    /**
     * @throws \PHPUnit\Framework\Exception
     * @throws \UnexpectedValueException
     */
    protected function buildFieldStrategyFactory(): FieldStrategyFactory
    {
        /** @var MapperFactory|MockObject $mockMapperFactory */
        $mockMapperFactory = $this->createMock(MapperFactory::class);

        $mockGradeMapper = $this->buildMockGradeMapper();
        $mockMapperFactory
            ->method('get')
            ->willReturn($mockGradeMapper);

        return new FieldStrategyFactory($mockMapperFactory, new CollectorFactory());
    }


    /**
     * @return Group\Mapper
     * @throws \UnexpectedValueException
     * @throws \PHPUnit\Framework\Exception
     */
    private function buildMockGradeMapper(): Group\Mapper
    {
        $gradeData = [
            1 => [
                ['1', '0', '5', 'E'],
                ['2', '10', '15', 'E+'],
                ['3', '20', '25', 'D'],
                ['4', '30', '35', 'D+'],
                ['5', '40', '45', 'C'],
                ['6', '50', '55', 'C+'],
                ['7', '60', '65', 'B'],
                ['8', '70', '75', 'B+'],
                ['9', '80', '85', 'A'],
                ['10', '90', '95', 'A+'],
            ],
            2 => [
                ['11', '0', '25', 'Not Satisfactory'],
                ['12', '50', '75', 'Satisfactory'],
            ],
            3 => [
                ['13', '0', '20', 'Needs Attention'],
                ['14', '40', '53', 'Acceptable'],
                ['15', '60', '68', 'Good'],
                ['16', '75', '88', 'Very Good'],
                ['17', '90', '95', 'Excellent'],
            ],
        ];

        $groupData = [
            1 => ['Standard'],
            2 => ['Satisfactory'],
            3 => ['Competency'],
        ];

        /** @var GenericCollection|Group\Model[] $groupModels */
        $groupModels = new GenericCollection(Group\Model::class);
        foreach ($groupData as $groupId => $group) {
            $groupModels[$groupId] = (new Group\Model())
                ->setId($groupId)
                ->setName($group[0]);

            $gradeModels = [];
            foreach ($gradeData[$groupId] as $grade) {
                $gradeModels[$grade[0]] = (new GradeLevel\Model())
                    ->setId((int)$grade[0])
                    ->setGroup($groupModels[$groupId])
                    ->setLowerBound((int)$grade[1])
                    ->setRawGrade((int)$grade[2])
                    ->setCaption($grade[3]);
            }
            $groupModels[$groupId]->setGradeLevels(new GenericCollection(GradeLevel\Model::class, $gradeModels));
        }

        /** @var Group\Mapper|MockObject $mockGradeMapper */
        $mockGradeMapper = $this->createMock(Group\Mapper::class);
        $mockGradeMapper
            ->method('findAll')
            ->willReturn($groupModels);
        return $mockGradeMapper;
    }


    /**
     * Returns a standard SelectLookup config
     *
     * @return Config
     */
    protected static function buildConfig(): Config
    {
        // SelectLookup
        $description =
            "The default letter grade group that is selected for new due work items.\n" .
            "  This group will also be used to determine the letter grade average,\n" .
            "  if there are no due work items that have a different group.";
        return (new Config())
            ->setId(218)
            ->setName('default_grade_group')
            ->setValue('1')
            ->setTitle('Default Letter Grade Group')
            ->setDescription($description)
            ->setFieldId(ConfigMapper::FIELD_SELECT_LOOKUP)
            ->setOptions('a:1:{s:6:"mapper";s:69:"\Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Mapper";}')
            ->setTypeId(ConfigMapper::TYPE_LMS_POLICY)
            ->setEditable(true)
            ->setHidden(false)
            ->setRequired(false)
            ->setModule(ConfigMapper::MODULE_SETTINGS);
    }
}
