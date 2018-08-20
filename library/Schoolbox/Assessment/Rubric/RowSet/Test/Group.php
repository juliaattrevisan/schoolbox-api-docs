<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\RowSet\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Rubric\RowSet\Criterion;

/**
 * @covers \Schoolbox\Assessment\Rubric\RowSet\Group
 */
class Group extends TestCase
{
    /**
     * @covers \Schoolbox\Assessment\Rubric\RowSet\Group::retrieveCriterionIds
     */
    public function testRetrieveCriterionIds(): void
    {
        $group = (new \Schoolbox\Assessment\Rubric\RowSet\Group())->setId(3);
        $group->getCriteria()
               ->add(new Criterion())
               ->add((new Criterion())->setId(9))
               ->add((new Criterion())->setId(8))
               ->add((new Criterion())->setId(7))
               ->add((new Criterion())->setId(5))
               ->add((new Criterion())->setId(6));
        $this->assertEquals([5, 6, 7, 8, 9,], $group->retrieveCriterionIds());
    }
}
