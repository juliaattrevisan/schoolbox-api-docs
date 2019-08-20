<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\User\Parent;

use Schoolbox\External\User;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\{Exception, DefinitionException};
use UnexpectedValueException;

class Mapper extends \Schoolbox\Mapper
{
    /**
     * @inheritDoc
     */
    protected function mapping(): void
    {
        $this->hasTable('sis_user_profile_parent');
        $this->hasModel(Model::class);
        $this->joins('sis_user_profile_id', User\Mapper::class, 'id', null, self::COLUMN_IDENTIFIER, 'user', 'user');
        $this->uses('mobile', self::TYPE_STRING);
        $this->uses('phone_home', self::TYPE_STRING);
        $this->uses('phone_work', self::TYPE_STRING);
        $this->uses('username', self::TYPE_STRING);
        $this->uses('email', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }

    /**
     * Return the first part of the string required to select from this mapper
     *
     * @return string
     */
    protected function getSelectString(): string
    {
        return "
            SELECT 
                {$this->getColumnString()},
                {$this->getJoinColumnString('user', 'u')}
        ";
    }

    /**
     * The Next Pieces
     *
     * @return string
     * @throws DefinitionException
     */
    protected function getFromString(): string
    {
        return "
            FROM {$this->getTable()}
            INNER JOIN {$this->getJoinSql('user', null, 'u')}
        ";
    }

    /**
     * @inheritdoc
     * @throws Exception
     * @throws DefinitionException
     * @throws UnexpectedValueException
     * @throws Exception
     */
    protected function hydrateSelect(\PDOStatement $statement): GenericCollection
    {
        $collection = $this->createCollection();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $staff = $this->hydrate($row);

            if ($staff) {
                $user = $this->getJoinMapper('user')->hydrate($row, 'u');
                $staff->setUser($user);
            }
            $collection->add($staff);
        }

        return $collection;
    }
}
