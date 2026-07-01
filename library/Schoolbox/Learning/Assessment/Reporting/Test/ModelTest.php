<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Reporting\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Schoolbox\Learning\Assessment\AbstractAssessment;
use Schoolbox\Learning\Assessment\Reporting\Model;

#[CoversClass(Model::class)]
class ModelTest extends TestCase
{
    public function testGetDefaultOpenSpanIgnoresPersistedDuration(): void
    {
        $assessment = (new Model())->setDefaultOpenSpan(14);

        $this->assertNull($assessment->getDefaultOpenSpan());
    }


    public function testSetDefaultOpenSpanDoesNotStoreDuration(): void
    {
        $assessment = new Model();

        $this->assertSame($assessment, $assessment->setDefaultOpenSpan(14));
        $this->assertNull($this->getStoredDefaultOpenSpan($assessment));
    }


    private function getStoredDefaultOpenSpan(Model $assessment): mixed
    {
        $property = new \ReflectionProperty(AbstractAssessment::class, 'defaultOpenSpan');

        return $property->getValue($assessment);
    }
}
