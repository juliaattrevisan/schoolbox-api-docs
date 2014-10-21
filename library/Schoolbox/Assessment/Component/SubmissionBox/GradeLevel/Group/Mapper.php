<?php
/**
 * Mapper for submission box grade groups.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group;

use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Collection;

class Mapper extends BaseMapper
{
    /**
     * {@inheritdoc}
     */
    protected function mapping()
    {
        $this->hasTable('submission_grade_group');
        $this->hasModel('\\Schoolbox\\Assessment\\Component\\SubmissionBox\\GradeLevel\\Group\\Model');
        $this->hasMap(
            array(
                'column'     => 'id',
                'property'   => 'id',
                'type'       => self::TYPE_INTEGER,
                'identifier' => true,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'name',
                'property' => 'name',
                'type'     => self::TYPE_STRING,
            )
        );
        $this->hasJoin(
            'levels',
            array(
                'mapper'    => '\\Schoolbox\\Assessment\\Component\\SubmissionBox\\GradeLevel\\Mapper',
                'this_key'  => 'id',
                'other_key' => 'submission_grade_group_id',
                'multiple'  => true,
            )
        );
    }

    /**
     * Loads all groups, along with their constituent grade levels.
     *
     * @return Collection|Model[]
     */
    public function findAllWithGradeLevels()
    {
        $query = "
            SELECT
                {$this->getColumnString('g')},
                {$this->getJoinColumnString('levels', 'l')}
            FROM `{$this->getTable()}` `g`
            INNER JOIN {$this->getJoinSql('levels', 'g', 'l')}
            ORDER BY `g`.`id` ASC, `l`.`lower_bound` ASC
        ";
        $statement = $this->getDatabase()->prepare($query);
        $statement->execute();

        // may as well just make an assoc array
        $groups = array();
        foreach ($statement as $row) {
            /** @var Model $item */
            $item = $this->hydrate($row, 'g');
            $id   = $item->getId();
            if (!array_key_exists($id, $groups)) {
                $groups[$id] = $item;
            }
            $groups[$id]->addGradeLevel($this->hydrateJoin($row, 'levels', 'l'));
        }
        return new Collection(array_values($groups));
    }
}
