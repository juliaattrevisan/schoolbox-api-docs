<?php

declare(strict_types=1);

namespace Schoolbox\Engagement\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Engagement\DueWork;
use Schoolbox\Learning\Assessment\IAssessable;

class DueWorkTest extends TestCase
{
    public function testVisibilityTableUsesSharedMarkVisibilityStrings(): void
    {
        $table = DueWork::createVisibilityTable(2);

        $this->assertSame([
            'Staff Only'                => [0, 0],
            'Staff & Student'           => [0, 0],
            'Staff & Parent'            => [0, 0],
            'Staff, Student & Parent'   => [0, 0],
        ], $table);
    }

    public function testVisibilityTotalsAreAddedUsingStringVisibilityKeys(): void
    {
        $table = DueWork::createVisibilityTable(2);

        $table = DueWork::addVisibilityTotalsToTable($table, [
            [
                'mark_visibility' => IAssessable::MARK_VISIBILITY_PARENT,
                'total'           => '3',
            ],
            [
                'mark_visibility' => IAssessable::MARK_VISIBILITY_PARENT_STUDENT,
                'total'           => '7',
            ],
        ], 1);

        $this->assertSame([0, '3'], $table['Staff & Parent']);
        $this->assertSame([0, '7'], $table['Staff, Student & Parent']);
    }
}
