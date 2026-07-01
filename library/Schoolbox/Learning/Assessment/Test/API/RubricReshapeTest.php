<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Test\API;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Schoolbox\Learning\Assessment\API\Helper;

#[CoversClass(Helper::class)]
class RubricReshapeTest extends TestCase
{
    public function testBuildTemplateRubricsGroupsRowsByInstanceWithTitleAndWeight(): void
    {
        $rows = [
            $this->templateRow(1, 'Written', '60.00', 10, 'Skills', 100, 'Communication', 1000, 'Developing', 4),
            $this->templateRow(1, 'Written', '60.00', 10, 'Skills', 100, 'Communication', 1001, 'Proficient', 5),
            $this->templateRow(2, 'Oral', '40.00', 20, 'Speaking', 200, 'Clarity', 2000, 'Clear', 3),
        ];

        $rubrics = $this->invokeStatic('buildTemplateRubrics', $rows);

        $this->assertCount(2, $rubrics);
        $this->assertSame(1, $rubrics[0]['id']);
        $this->assertSame('Written', $rubrics[0]['title']);
        $this->assertSame(60.0, $rubrics[0]['weight']);
        $this->assertCount(1, $rubrics[0]['capabilities']);
        $this->assertSame(10, $rubrics[0]['capabilities'][0]['id']);
        $this->assertCount(2, $rubrics[0]['capabilities'][0]['indicators'][0]['descriptors']);
        $this->assertSame('Oral', $rubrics[1]['title']);
        $this->assertSame(40.0, $rubrics[1]['weight']);
    }

    public function testBuildTemplateRubricsReturnsEmptyArrayForNoRows(): void
    {
        $this->assertSame([], $this->invokeStatic('buildTemplateRubrics', []));
    }

    public function testFirstRubricAsLegacyReturnsFirstCapabilitiesOnly(): void
    {
        $rubrics = [
            ['id' => 1, 'title' => 'Written', 'weight' => 60.0, 'capabilities' => [['id' => 10]]],
            ['id' => 2, 'title' => 'Oral', 'weight' => 40.0, 'capabilities' => [['id' => 20]]],
        ];

        $this->assertSame(['capabilities' => [['id' => 10]]], $this->invokeStatic('firstRubricAsLegacy', $rubrics));
    }

    public function testFirstRubricAsLegacyReturnsNullForEmpty(): void
    {
        $this->assertNull($this->invokeStatic('firstRubricAsLegacy', []));
    }

    /**
     * Build a single flat v_rubric template row with all keys buildTemplateRubrics reads.
     */
    private function templateRow(
        int $instanceId,
        string $instanceTitle,
        string $instanceWeight,
        int $capabilityId,
        string $capabilityName,
        int $indicatorId,
        string $indicatorName,
        int $descriptorId,
        string $descriptorDescription,
        int $descriptorSequence,
    ): array {
        return [
            'rubric_instance_id'     => $instanceId,
            'rubric_instance_title'  => $instanceTitle,
            'rubric_instance_weight' => $instanceWeight,
            'capability_id'          => $capabilityId,
            'capability_name'        => $capabilityName,
            'capability_max_value'   => '0',
            'indicator_id'           => $indicatorId,
            'indicator_name'         => $indicatorName,
            'indicator_max_value'    => '0',
            'descriptor_id'          => $descriptorId,
            'descriptor_descriptor'  => $descriptorDescription,
            'descriptor_max_value'   => '0',
            'descriptor_sequence'    => $descriptorSequence,
        ];
    }

    private function invokeStatic(string $method, array $arg): mixed
    {
        $reflection = new ReflectionMethod(Helper::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke(null, $arg);
    }
}
