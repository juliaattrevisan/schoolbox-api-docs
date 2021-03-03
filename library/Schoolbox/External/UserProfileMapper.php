<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External;

use PDOStatement;
use Schoolbox;
use Schoolbox\UserMapper;

/**
 * Mapper for user profile objects.
 *
 * This is only used by the Schoolbox connector.
 *
 * @method UserProfile createModel($idValue = null)
 * @method UserProfile findById($identifierValue)
 * @method UserProfile findOneByColumn($columnName, $columnValue, array $options = [])
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
        $this->joins(
            'user_id',
            UserMapper::class,
            'id',
            null,
            self::COLUMN_IDENTIFIER,
            'user',
            'user'
        );
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
        return $this->findOneByColumn('u.synergy_id', $externalId);
    }

    /**
     * Include the user object in any profiles retrieved.
     *
     * @return string
     */
    protected function getSelectString()
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('user', 'u')}
        ";
    }

    /**
     * Include the user object in any profiles retrieved.
     *
     * @return string
     */
    protected function getFromString()
    {
        return "
            FROM {$this->database->getQuoter()->quoteIdentifier($this->getTable())}
            INNER JOIN {$this->getJoinSql('user', '', 'u')}
        ";
    }

    /**
     * Include the user object in any profiles retrieved.
     *
     * @param PDOStatement $statement
     * @return Schoolbox\GenericCollection|UserProfile[]
     * @throws Schoolbox\Mapper\Exception
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $result = $this->createCollection();
        foreach ($statement as $row) {
            /** @var UserProfile $profile */
            $profile = $this->hydrate($row);
            $profile->setUser($this->hydrateJoin($row, 'user', 'u'));
            $result[$row['user_id']] = $profile;
        }
        return $result;
    }
}
