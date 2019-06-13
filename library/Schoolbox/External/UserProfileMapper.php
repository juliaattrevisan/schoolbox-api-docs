<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright 2013 Alaress Pty Ltd
 * @link      http://www.alaress.com.au
 */

namespace Schoolbox\External;

use Schoolbox;
use Schoolbox\UserMapper;

/**
 * Mapper for user profile objects.
 *
 * This is only used by the Schoolbox connector.
 *
 * @method UserProfile findOneByColumn($columnName, $columnValue, $order = [], $options = [])
 */
class UserProfileMapper extends Schoolbox\Mapper
{

    /**
     * {@inheritdoc}
     */
    protected function mapping()
    {
        $this->hasTable('user_profile');
        $this->hasModel(UserProfile::class);
        $this->uses('user_id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('dob', self::TYPE_DATE);
        $this->uses('level', self::TYPE_STRING);
        $this->uses('homegroup', self::TYPE_STRING);
        $this->uses('house', self::TYPE_STRING);
        $this->uses('guardian1Name', self::TYPE_STRING);
        $this->uses('guardian1HomePhone', self::TYPE_STRING);
        $this->uses('guardian1WorkPhone', self::TYPE_STRING);
        $this->uses('guardian1MobilePhone', self::TYPE_STRING);
        $this->uses('guardian1Email', self::TYPE_STRING);
        $this->uses('guardian2Name', self::TYPE_STRING);
        $this->uses('guardian2HomePhone', self::TYPE_STRING);
        $this->uses('guardian2WorkPhone', self::TYPE_STRING);
        $this->uses('guardian2MobilePhone', self::TYPE_STRING);
        $this->uses('guardian2Email', self::TYPE_STRING);
        $this->uses('title', self::TYPE_STRING);
        $this->uses('given_name', self::TYPE_STRING);
        $this->uses('preferred_name', self::TYPE_STRING);
        $this->uses('last_name', self::TYPE_STRING);
        $this->uses('gender', self::TYPE_STRING);
        $this->uses('email', self::TYPE_STRING);
        $this->hasJoin(
            'user',
            [
                'mapper'    => UserMapper::class,
                'this_key'  => 'user_id',
                'other_key' => 'id',
            ]
        );
    }

    /**
     * Gets a user profile object from the external ID of the user.
     *
     * @param string $externalId
     * @return UserProfile
     * @throws Schoolbox\Mapper\Exception
     * @throws Schoolbox\Mapper\Exception\Deleted
     */
    public function findByExternalId(string $externalId): UserProfile
    {
        return $this->findOneByColumn('user.synergy_id', $externalId);
    }
}
