<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral\Comment;

use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\Model as Pastoral;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * @method Model createModel($idValue = null)
 */
class Mapper extends BaseMapper
{
    /**
     * Setup mapper
     */
    protected function mapping()
    {
        $this->hasTable('pastoral_comment');
        $this->hasModel(Model::class);
        $this->joins('owner_id', UserMapper::class, 'id', null, null, 'owner', 'owner');
        $this->joins('pastoral_id', PastoralMapper::class, 'id', null, null, 'pastoral', 'pastoral');
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('external_code', self::TYPE_STRING);
        $this->uses('body', self::TYPE_STRING);
        $this->uses('private', self::TYPE_BOOLEAN);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }

    /**
     * @inheritdoc
     */
    protected function getSelectString()
    {
        return "
            SELECT      {$this->getColumnString()},
                        {$this->getJoinColumnString('owner', 'o')},
                        {$this->getJoinColumnString('pastoral', 'p')}
            FROM        {$this->getTable()} 
            LEFT JOIN   {$this->getJoinSql('owner', '', 'o')}
            LEFT JOIN   {$this->getJoinSql('pastoral', '', 'p')}
        ";
    }

    /**
     * Filter records from the collection if the current user doesn't have access to them.
     * If the user is not in the audience and is not the owner, they can't see the record.
     *
     * @param Collection|Model[] $records A list of pastoral records
     * @param User               $user    The user who is trying to view them
     *
     * @return Collection|Model[]
     */
    public function filterRecordsForUser(Collection $records, User $user)
    {
        $collection = $this->createCollection();

        /** @var Model $record */
        foreach ($records as $record) {
            $record = $this->filterRecordForUser($record, $user);

            if ($record instanceof Model) {
                $collection->add($record);
            }
        }

        return $collection;
    }

    /**
     * Filter records from the collection if the current user doesn't have access to them.
     * If the user is not in the audience and is not the owner, they can't see the record.
     *
     * @param Model $record A pastoral comment
     * @param User  $user   The user who is trying to view them
     *
     * @return Model|null
     */
    public function filterRecordForUser(Model $record, User $user)
    {
        // Pastoral moderators get all comments

        if ($user->getAccessList()->filterBy('service', UserAccess::SERVICE_PASTORAL)->count() > 0) {
            return $record;
        }

        // Owners can see their comments

        if ($user->getId() == $record->getOwner()->getId()) {
            return $record;
        }

        // Work out users access to the pastoral record

        $pastoral = $record->getPastoral();

        $accessLevel = PastoralAccessMapper::ACCESS_NONE;

        foreach ($pastoral->getAccess() as $access) {
            $accessLevelCurrent = $access->getAccessLevel($user);
            if ($accessLevelCurrent >= $accessLevel) {
                $accessLevel = $accessLevelCurrent;
            }
            if ($accessLevel >= PastoralAccessMapper::ACCESS_PRIVATE) {
                break;
            }
        }

        if ($record->getPrivate()) {
            $accessGranted = $accessLevel >= PastoralAccessMapper::ACCESS_PRIVATE;
        } else {
            $accessGranted = $accessLevel >= PastoralAccessMapper::ACCESS_PUBLIC;
        }

        if ($accessGranted) {
            return $record;
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        // Return pastoral records fully hydrated by default

        $collection = $this->createCollection();

        foreach ($statement as $row) {
            /** @var Model $model */
            $model = $this->hydrate($row);

            /** @var User $owner */
            $owner = $this->hydrateJoin($row, 'owner', 'o');
            $model->setOwner($owner);

            /** @var Pastoral $pastoral */
            $pastoral = $this->hydrateJoin($row, 'pastoral', 'p');

            $model->setPastoral($pastoral);

            $collection->add($model);
        }

        return $collection;
    }
}
