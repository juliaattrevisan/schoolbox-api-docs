<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral;

use Schoolbox\Collection;
use Schoolbox\Mapper as SchoolboxMapper;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;
use Schoolbox\Pastoral\Comment\Mapper as PastoralCommentMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;

/**
 * Class BaseMapper
 *
 * @package Schoolbox\Pastoral
 */
abstract class BaseMapper extends SchoolboxMapper
{
    /**
     * Filter records from the collection if the current user doesn't have access to them.
     * If the user is not in the audience and is not the owner, they can't see the record.
     *
     * @param Model $record A pastoral record
     * @param User  $user   The user who is trying to view them
     *
     * @return Model|null
     */
    public function filterRecordForUser(Model $record, User $user)
    {
        if (!$record instanceof Model) {
            return null;
        }

        if ($this->isUserInPastoralService($user) || $user->getId() == $record->getOwner()->getId()) {
            // Pastoral moderator and owner see all, shortcut out of here
            return $record;
        }

        // We need to iterate all access records to see if any of them grants private access

        $accessLevel = $record->getAccessLevel($user);

        // If denied, no soup for you!

        if ($accessLevel == PastoralAccessMapper::ACCESS_DENY) {
            return null;
        }

        // Only has public access? Strip the private body

        if ($accessLevel == PastoralAccessMapper::ACCESS_PUBLIC) {
            $record->setBodyPrivate("");
        }

        // And filter the comments on the record, because this is planet Earth and we don't trust anybody
        /** @var PastoralCommentMapper $pastoralCommentMapper */
        $pastoralCommentMapper = $this->getJoinMapper('comments');
        $record->setComments($pastoralCommentMapper->filterRecordsForUser($record->getComments(), $user));

        return $record;
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
            $collection->add($this->filterRecordForUser($record, $user));
        }

        return $collection;
    }

    /**
     * Does the given user have access to the pastoral service?
     *
     * @param User $user
     *
     * @return bool
     */
    protected function isUserInPastoralService(User $user)
    {
        return $user->getAccessList()->filterBy('service', UserAccess::SERVICE_PASTORAL)->count() > 0;
    }
}
