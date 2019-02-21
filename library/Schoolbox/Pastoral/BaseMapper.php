<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral;

use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as SchoolboxMapper;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;
use Schoolbox\Pastoral\Comment\Mapper as PastoralCommentMapper;
use Schoolbox\Pastoral\Comment\Model as PastoralComment;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;

/**
 * Class BaseMapper
 *
 * @package Schoolbox\Pastoral
 * @method GenericCollection|Model[] createCollection($contents = [])
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
    private function filterRecordForUser(Model $record, User $user): ?Model
    {
        if (!$record instanceof Model) {
            return null;
        }

        // Don't allow records to be filtered more than once.
        // This is to avoid recursion when filtering related records.
        if ($record->isFiltered()) {
            return $record;
        }

        $record->setFiltered(true);

        // We need to iterate all access records to see if any of them grants private access

        $accessLevel = $record->getAccessLevel($user);

        // If denied, no soup for you!
        //
        // We ignore pastoral moderators and the record owner get ignored by this, otherwise they'll never get able to
        // update an erroneous Deny access level added to a record

        if ($accessLevel == PastoralAccessMapper::ACCESS_DENY) {
            return null;
        }

        // Only has public access? Strip the private body and files

        if ($accessLevel == PastoralAccessMapper::ACCESS_PUBLIC) {
            // Pastoral moderators and Record owner ignored
            $record->setBodyPrivate("");
            $record->setAttachments($record->getAttachments()->filterBy('private', false));
        }

        // Filter out related records, too
        $record->setRelatedRecords($this->filterRecordsForUser($record->getRelatedRecords(), $user));

        // And filter the comments on the record, because this is planet Earth and we don't trust anybody
        /** @var PastoralCommentMapper $pastoralCommentMapper */
        $pastoralCommentMapper = $this->getJoinMapper('comments');
        $record->setComments($pastoralCommentMapper->filterRecordsForUser($record->getComments(), $user));

        // Set the 'updatedAtDisplay' value now that we have all of the comments

        /** @var PastoralComment $comment */
        foreach ($record->getComments() as $comment) {
            if ($comment->getUpdatedAt()->gt($record->getUpdatedAtDisplay())) {
                // Found a newer comment, update the record with the date/time, and author of the comment
                $record->setUpdatedAtDisplay($comment->getUpdatedAt());
                $record->setModifiedByDisplay($comment->getOwner());
            }
        }

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

        foreach ($records as $record) {
            $filteredRecord = $this->filterRecordForUser($record, $user);
            if ($filteredRecord !== null) {
                $collection->add($filteredRecord);
            }
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
