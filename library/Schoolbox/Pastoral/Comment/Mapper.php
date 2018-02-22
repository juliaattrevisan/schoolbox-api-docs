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
            INNER JOIN  {$this->getJoinSql('owner', '', 'o', true)}
            LEFT JOIN   {$this->getJoinSql('pastoral', '', 'p')}
        ";
    }

    /**
     * Filter records from the collection if the current user doesn't have access to them.
     * If the user is not in the audience and is not the owner, they can't see the record.
     *
     * @param Collection|Model[] $comments A list of pastoral comments
     * @param User               $user    The user who is trying to view them
     *
     * @return Collection|Model[]
     */
    public function filterRecordsForUser(Collection $comments, User $user)
    {
        $collection = $this->createCollection();

        /** @var Model $comment */
        foreach ($comments as $comment) {
            $comment = $this->filterRecordForUser($comment, $user);

            if ($comment instanceof Model) {
                $collection->add($comment);
            }
        }

        return $collection;
    }

    /**
     * Filter records from the collection if the current user doesn't have access to them.
     * If the user is not in the audience and is not the owner, they can't see the record.
     *
     * @param Model $comment A pastoral comment
     * @param User  $user   The user who is trying to view them
     *
     * @return Model|null
     */
    public function filterRecordForUser(Model $comment, User $user)
    {
        // Pastoral moderators get all comments

        if ($user->getAccessList()->filterBy('service', UserAccess::SERVICE_PASTORAL)->count() > 0) {
            return $comment;
        }

        // Owners can see their comments

        if ($user->getId() == $comment->getOwner()->getId() || $user->getId() == $comment->getPastoral()->getOwner()->getId()) {
            return $comment;
        }

        // Work out users access to the pastoral record

        $accessLevel = $comment->getPastoral()->getAccessLevel($user);

        // Deny access? No soup for you!
        if ($accessLevel == PastoralAccessMapper::ACCESS_DENY) {
            return null;
        }

        // Return comment if its private (and we have private access) or it is public and we have at least public access

        if ($comment->getPrivate() && $accessLevel >= PastoralAccessMapper::ACCESS_PRIVATE ||
            !$comment->getPrivate() && $accessLevel >= PastoralAccessMapper::ACCESS_PUBLIC) {
            return $comment;
        }

        // Didn't have access. No soup for you!
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
