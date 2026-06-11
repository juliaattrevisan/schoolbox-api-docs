<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\BulkFeedbackImport\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Schoolbox\Admin\BulkFeedbackImport\Strategy\Mark\HumanReadableMark;
use Schoolbox\Learning\Assessment\Reporting\Model as Reporting;
use Schoolbox\Learning\DueWork\StateMapper;
use Schoolbox\Learning\MarkScheme\None;

#[CoversClass(HumanReadableMark::class)]
class HumanReadableMarkTest extends TestCase
{
    public function testCalculatePreservesFreeTextGradeForLearners(): void
    {
        $strategy = new HumanReadableMark($this->createStub(StateMapper::class));
        $assessment = (new Reporting())
            ->setInstructorMarkScheme(new None())
            ->setMarkScheme(new None());

        $result = $strategy->calculate([
            HumanReadableMark::DISCRIMINATOR_COLUMN => '14 days',
        ], $assessment);

        $this->assertSame([
            'normalisedMark' => null,
            'learnerHumanReadableMark' => '14 days',
            'instructorHumanReadableMark' => '14 days',
        ], $result);
    }
}
