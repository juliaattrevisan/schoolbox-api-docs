<?php
/**
 * Mapper for relations between users and classes.
 *
 * This is only used by the Schoolbox connector.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright 2013 Alaress Pty Ltd
 * @link      http://www.alaress.com.au
 */

namespace Schoolbox\External;
use Schoolbox;

class ClassInvolvementMapper extends Schoolbox\External\Mapper {

    /**
     * {@inheritdoc}
     */
    protected function mapping() {
        $this->hasTable('class_user');
        $this->hasModel('\\Schoolbox\\External\\ClassInvolvement');
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
            'column'   => 'user_id',
            'property' => 'user',
            'type'     => self::TYPE_MODEL,
            'join'     => 'user',
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
            'user',
            array(
                'mapper'    => '\\Schoolbox\\UserMapper',
                'this_key'  => 'user_id',
                'other_key' => 'id',
            )
        );
    }

    /**
     * Removes all involvements for a given user type (staff, parents, teachers)
     * from the given class.
     *
     * @param  ClassEntry $class
     * @param  string     $group_type
     * @return int        The number of rows deleted.
     */
    public function deleteInvolvementsForClassAndGroupType(ClassEntry $class, $group_type) {
        $query = "
            DELETE `ui` FROM `{$this->getTable()}` `ui`
            INNER JOIN {$this->getJoinSql('user', 'ui', 'u')}
            INNER JOIN `groups` `g` ON `u`.`group_id` = `g`.`id`
            WHERE `g`.`group_type` = :group_type AND `ui`.`class_id` = :class
        ";
        $statement = $this->database->prepare($query);
        $statement->execute(array('class' => $class->getId(), 'group_type' => $group_type));
        return $statement->rowCount();
    }

}
