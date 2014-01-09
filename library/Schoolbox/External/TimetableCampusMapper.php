<?php
/**
 * Mapper for class objects.
 *
 * This is only used by the Schoolbox connector.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright 2013 Alaress Pty Ltd
 * @link      http://www.alaress.com.au
 */

namespace Schoolbox\External;

use Schoolbox;
use Schoolbox\Campus;

class TimetableCampusMapper extends Schoolbox\External\Mapper {

    /**
     * {@inheritdoc}
     */
    protected function mapping() {
        $this->hasTable('timetable_campus');
        $this->hasModel('\\Schoolbox\\External\\TimetableCampus');
        $this->hasMap(array(
            'column'     => 'cycle_id',
            'property'   => 'cycle',
            'type'       => self::TYPE_MODEL,
            'join'       => 'cycle',
            'identifier' => true,
        ));
        $this->hasMap(array(
            'column'   => 'campus_id',
            'property' => 'campus',
            'type'     => self::TYPE_MODEL,
            'join'     => 'campus',
        ));
        $this->hasMap(array(
            'column'   => 'start',
            'property' => 'start',
            'type'     => self::TYPE_DATE,
        ));
        $this->hasMap(array(
            'column'   => 'reset',
            'property' => 'reset',
            'type'     => self::TYPE_DATE,
        ));
        $this->hasMap(array(
            'column'   => 'finish',
            'property' => 'finish',
            'type'     => self::TYPE_DATE,
        ));
        $this->hasMap(array(
            'column'   => 'offset',
            'property' => 'offset',
            'type'     => self::TYPE_INTEGER,
        ));
        $this->hasJoin(
            'cycle',
            array(
                'mapper'    => '\\Schoolbox\\External\\TimetableCycleMapper',
                'this_key'  => 'cycle_id',
                'other_key' => 'id',
            )
        );
        $this->hasJoin(
            'campus',
            array(
                'mapper'    => '\\Schoolbox\\CampusMapper',
                'this_key'  => 'campus_id',
                'other_key' => 'id',
            )
        );
    }

    /**
     * Gets the TimetableCampus object for this campus and date.
     *
     * @param Campus $campus
     * @param string $date   The date to get the campus for, defaults to now.
     *
     * @return TimetableCampus
     */
    public function findCurrentTimetable(Campus $campus, $date = 'now') {
        /** @var TimetableCycleMapper $cycle_mapper */
        $cycle_mapper  = $this->getJoinMapper('cycle');
        /** @var Schoolbox\CampusMapper $cycle_mapper */
        $campus_mapper = $this->getJoinMapper('campus');
        $query = "
            SELECT
                {$this->getColumnString('tc')},
                {$cycle_mapper->getColumnString('ty')},
                {$campus_mapper->getColumnString('ca')}
            FROM `{$this->getTable()}` `tc`
            INNER JOIN {$this->getJoinSql('cycle',  'tc', 'ty')}
            INNER JOIN {$this->getJoinSql('campus', 'tc', 'ca')}
            WHERE `tc`.`start` <= :date AND `tc`.`finish` >= :date AND `tc`.`campus_id` = :campus
        ";
        $statement = $this->database->prepare($query);

        // Date: depends on what is passed
        $date = new \DateTime($date);
        $statement->bindValue('campus', $campus->getId());
        $statement->bindValue('date',   $date->format('Y-m-d'));
        $statement->execute();

        if ($row = $statement->fetch()) {
            /** @var TimetableCampus $timetable_campus **/
            $timetable_campus = $this->hydrate($row, 'tc');
            $timetable_campus
                ->setCycle($cycle_mapper->hydrate($row, 'ty'))
                ->setCampus($campus_mapper->hydrate($row, 'ca'))
            ;
            return $timetable_campus;
        }
        return false;
    }
}
