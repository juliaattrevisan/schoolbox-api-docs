<?php
/**
 * Mapper for user profile objects.
 *
 * This is only used by the Schoolbox connector.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright 2013 Alaress Pty Ltd
 * @link      http://www.alaress.com.au
 */

namespace Schoolbox\External;
use Schoolbox;

class UserProfileMapper extends Schoolbox\Mapper {

    /**
     * {@inheritdoc}
     */
    protected function mapping() {
        $this->hasTable('user_profile');
        $this->hasModel('\\Schoolbox\\External\\UserProfile');
        $this->hasMap(array(
            'column'     => 'user_id',
            'property'   => 'userId',
            'type'       => self::TYPE_INTEGER,
            'identifier' => true
        ));
        $this->hasMap(array(
            'column'   => 'dob',
            'property' => 'dob',
            'type'     => self::TYPE_DATE,
        ));
        $this->hasMap(array(
            'column'   => 'level',
            'property' => 'level',
            'type'     => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'   => 'homegroup',
            'property' => 'homegroup',
            'type'     => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'   => 'house',
            'property' => 'house',
            'type'     => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'   => 'guardian1Name',
            'property' => 'guardian1Name',
            'type'     => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'   => 'guardian1HomePhone',
            'property' => 'guardian1HomePhone',
            'type'     => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'   => 'guardian1WorkPhone',
            'property' => 'guardian1WorkPhone',
            'type'     => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'   => 'guardian1MobilePhone',
            'property' => 'guardian1MobilePhone',
            'type'     => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'   => 'guardian1Address',
            'property' => 'guardian1Address',
            'type'     => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'   => 'guardian1Suburb',
            'property' => 'guardian1Suburb',
            'type'     => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'   => 'guardian1Postcode',
            'property' => 'guardian1Postcode',
            'type'     => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'   => 'guardian1Email',
            'property' => 'guardian1Email',
            'type'     => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'   => 'guardian1MailAddress',
            'property' => 'guardian1MailAddress',
            'type'     => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'   => 'guardian2Name',
            'property' => 'guardian2Name',
            'type'     => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'   => 'guardian2HomePhone',
            'property' => 'guardian2HomePhone',
            'type'     => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'   => 'guardian2WorkPhone',
            'property' => 'guardian2WorkPhone',
            'type'     => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'   => 'guardian2MobilePhone',
            'property' => 'guardian2MobilePhone',
            'type'     => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'   => 'guardian2Address',
            'property' => 'guardian2Address',
            'type'     => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'   => 'guardian2Suburb',
            'property' => 'guardian2Suburb',
            'type'     => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'   => 'guardian2Postcode',
            'property' => 'guardian2Postcode',
            'type'     => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'   => 'guardian2Email',
            'property' => 'guardian2Email',
            'type'     => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'   => 'guardian2MailAddress',
            'property' => 'guardian2MailAddress',
            'type'     => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'   => 'title',
            'property' => 'title',
            'type'     => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'   => 'given_name',
            'property' => 'givenName',
            'type'     => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'   => 'preferred_name',
            'property' => 'preferredName',
            'type'     => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'   => 'last_name',
            'property' => 'lastName',
            'type'     => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'   => 'birth_date',
            'property' => 'birthDate',
            'type'     => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'   => 'gender',
            'property' => 'gender',
            'type'     => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'   => 'email',
            'property' => 'email',
            'type'     => self::TYPE_STRING,
        ));

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
     * Gets a user profile object from the external ID of the user.
     *
     * @param  string      $external_id
     * @return UserProfile or false if not found
     */
    public function findByExternalId($external_id) {
        $user_join = $this->getJoinMapper('user');
        $query     = "
            SELECT {$this->getColumnString('up')}, {$user_join->getColumnString('u')}
            FROM `{$this->getTable()}` `up`
            INNER JOIN {$this->getJoinSql('user', 'up', 'u')}
            WHERE `u`.`synergy_id` = ?
        ";
        $statement = $this->database->prepare($query);
        $statement->execute(array($external_id));

        if ($row = $statement->fetch()) {
            return $this->hydrate($row, 'up');
        }
        // TODO in develop, make this throw exception on not found
        return false;
    }
}
