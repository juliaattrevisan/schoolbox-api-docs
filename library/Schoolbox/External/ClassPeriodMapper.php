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

class ClassPeriodMapper extends Schoolbox\Mapper {

    /**
     * {@inheritdoc}
     */
    protected function mapping() {
        $this->hasTable('sis_class_period');
        $this->hasModel(ClassPeriod::class);


        $this->hasMap(array(
            'column'     => 'id',
            'property'   => 'id',
            'type'       => self::TYPE_INTEGER,
            'identifier' => true
        ));


        $this->hasMap(array(
            'column'   => 'class_id',
            'property' => 'class',
            'type'     => self::TYPE_MODEL,
            'join'     => 'class',
        ));


        $this->hasMap(array(
            'column'   => 'period_id',
            'property' => 'period',
            'type'     => self::TYPE_MODEL,
            'join'     => 'period',
        ));


        $this->hasMap([
            'column'   => 'teacher_external_id',
            'property' => 'teacher',
            'type'     => self::TYPE_STRING,
        ]);


        $this->hasMap(array(
            'column'   => 'room',
            'property' => 'room',
            'type'     => self::TYPE_STRING,
        ));


        $this->hasJoin(
            'class',
            array(
                'mapper'    => '\\Schoolbox\\External\\ClassEntryMapper',
                'this_key'  => 'class_id',
                'other_key' => 'id',
            )
        );


        $this->hasJoin(
            'period',
            array(
                'mapper'    => '\\Schoolbox\\External\\TimetablePeriodMapper',
                'this_key'  => 'period_id',
                'other_key' => 'id',
            )
        );
    }

    /**
     * Clears all timetable period entries for this class.
     *
     * @param ClassEntry      $class
     * @param TimetablePeriod $period
     *
     * @return int
     */
    public function deleteForClassAndPeriod(ClassEntry $class, TimetablePeriod $period) {
        $query = "DELETE FROM `{$this->getTable()}` WHERE `class_id` = :class AND `period_id` = :cycle";
        $statement = $this->database->prepare($query);
        $statement->execute(array('class' => $class->getId(), 'cycle' => $period->getId()));
        return $statement->rowCount();
    }
}
