<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\RowSet\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Rubric\RowSet\Criterion;
use Schoolbox\Assessment\Rubric\RowSet\Group;
use Schoolbox\Resource\Folder\Model as Folder;

/**
 * @covers \Schoolbox\Assessment\Rubric\RowSet\RowSet
 */
class RowSet extends TestCase
{
    /**
     * @covers \Schoolbox\Assessment\Rubric\RowSet\RowSet::retrieveGroupIds
     */
    public function testRetrieveGroupIds(): void
    {
        $rowSet = new \Schoolbox\Assessment\Rubric\RowSet\RowSet(new Folder());
        $rowSet->getGroups()
               ->add(new Group())
               ->add((new Group())->setId(3))
               ->add((new Group())->setId(5))
               ->add((new Group())->setId(5))
               ->add((new Group())->setId(6))
               ->add((new Group())->setId(1));
        $this->assertEquals([1, 3, 5, 6,], $rowSet->retrieveGroupIds());
    }


    /**
     * @covers \Schoolbox\Assessment\Rubric\RowSet\RowSet::retrieveCriterionIds
     */
    public function testRetrieveCriterionIds(): void
    {
        $rowSet = new \Schoolbox\Assessment\Rubric\RowSet\RowSet(new Folder());

        $group1 = (new Group())->setId(3);
        $group1->getCriteria()
               ->add(new Criterion())
               ->add((new Criterion())->setId(5))
               ->add((new Criterion())->setId(6))
               ->add((new Criterion())->setId(7))
               ->add((new Criterion())->setId(8))
               ->add((new Criterion())->setId(9));

        $group2 = (new Group())->setId(10);
        $group2->getCriteria()
               ->add(new Criterion())
               ->add((new Criterion())->setId(5))
               ->add((new Criterion())->setId(16))
               ->add((new Criterion())->setId(17))
               ->add((new Criterion())->setId(18))
               ->add((new Criterion())->setId(19));

        $group3 = (new Group())->setId(12);

        $rowSet->getGroups()
               ->add($group1)
               ->add($group2)
               ->add($group3);
        $this->assertEquals([5, 6, 7, 8, 9, 16, 17, 18, 19,], $rowSet->retrieveCriterionIds());
    }
}
