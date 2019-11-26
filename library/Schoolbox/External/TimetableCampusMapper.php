<?php
/**
 * Mapper for class objects.
 *
 * This is only used by the Schoolbox connector.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\External;

use Schoolbox;
use Schoolbox\Campus;

class TimetableCampusMapper extends Schoolbox\Mapper {

    /**
     * {@inheritdoc}
     */
    protected function mapping() {
        $this->hasTable('sis_timetable_campus');
        $this->hasModel(TimetableCampus::class);
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
        $this->hasMap(array(
            'column'   => 'start_day',
            'property' => 'startDay',
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
     * @throws Schoolbox\Mapper\Exception if no timetable is found
     * @return TimetableCampus
     */
    public function findCurrentTimetable(Campus $campus, $date = 'now') {
        /** @var TimetableCycleMapper $cycle_mapper */
        $cycle_mapper  = $this->getJoinMapper('cycle');
        /** @var Schoolbox\CampusMapper $cycle_mapper */
        $campus_mapper = $this->getJoinMapper('campus');
        // Find first timetable whose end is after $date.
        $query = "
            SELECT
                {$this->getColumnString('tc')},
                {$cycle_mapper->getColumnString('ty')},
                {$campus_mapper->getColumnString('ca')}
            FROM `{$this->getTable()}` `tc`
            INNER JOIN {$this->getJoinSql('cycle',  'tc', 'ty')}
            INNER JOIN {$this->getJoinSql('campus', 'tc', 'ca')}
            WHERE `tc`.`finish` >= :date AND `tc`.`campus_id` = :campus
            ORDER BY `tc`.`finish` ASC
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
        throw new Schoolbox\Mapper\Exception\NotFound();
    }

    /**
     * Get a single entry by its name and campus code.
     *
     * @param string $name
     * @param string $campusCode
     *
     * @throws Schoolbox\Mapper\Exception if no timetable is found
     * @return TimetableCampus
     */
    public function findOneByNameAndCampusCode($name, $campusCode)
    {
        $query = "
            SELECT {$this->getColumnString('tc')}
            FROM   {$this->getTable()} `tc`
            INNER JOIN {$this->getJoinSql('cycle',  'tc', 'ty')}
            INNER JOIN {$this->getJoinSql('campus', 'tc', 'ca')}
            WHERE `ca`.`ext_timetable_code` = :campusCode
            AND   `ty`.`title`              = :name
        ";
        $statement = $this->database->prepare($query);

        $statement->bindValue('campusCode', $campusCode);
        $statement->bindValue('name',       $name);
        $statement->execute();

        if ($row = $statement->fetch()) {
            return $this->hydrate($row, 'tc');
        }
        throw new Schoolbox\Mapper\Exception\NotFound();
    }
}
