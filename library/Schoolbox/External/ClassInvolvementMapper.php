<?php
/**
 * Mapper for relations between users and classes.
 *
 * This is only used by the Schoolbox connector.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\External;
use Schoolbox;

class ClassInvolvementMapper extends Schoolbox\Mapper {

    /**
     * {@inheritdoc}
     */
    protected function mapping() {
        $this->hasTable('sis_class_user');
        $this->hasModel(ClassInvolvement::class);
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


        $this->hasMap([
            'column'   => 'external_id',
            'property' => 'externalId',
            'type'     => self::TYPE_STRING,
        ]);


        $this->hasJoin(
            'class',
            array(
                'mapper'    => '\\Schoolbox\\External\\ClassEntryMapper',
                'this_key'  => 'class_id',
                'other_key' => 'id',
            )
        );
    }

    /**
     * Removes all involvements from the given class.
     *
     * @param  ClassEntry $class
     * @return int        The number of rows deleted.
     */
    public function deleteInvolvementsForClass(ClassEntry $class) {
        $query = "DELETE FROM `{$this->getTable()}` WHERE `class_id` = :class";
        $statement = $this->database->prepare($query);
        $statement->execute(array('class' => $class->getId()));
        return $statement->rowCount();
    }
}
