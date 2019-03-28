<?php
/**
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Pastoral;

use Schoolbox\Mapper\Exception\InsufficientHydrationException;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;
use Schoolbox\Collection;

/**
 * Responsible for stripping the content/relationships from a pastoral record that a
 * specific user does not have sufficient permission to access.
 *
 * ** THIS WILL INTENTIONALLY MUTATE THE PASTORAL MODELS PASSED TO IT **
 */
class AccessLevelCensor
{

    /**
     * Filters records and record content that the specified user is not permitted to view.
     *
     * * Records the user is NOT permitted to see are removed completely from graph, becoming NULL
     * * Records the user IS permitted to see have their content filtered appropriately
     *
     * Public-facing recursive driver.
     *
     * @param Collection|Model[] $records A list of pastoral records
     * @param User               $user    The user who is trying to view them
     *
     * @return Collection|Model[]
     * @throws InsufficientHydrationException
     */
    public function filterRecordsForUser(Collection $records, User $user): Collection
    {
        $resultCache = new Collection();
        foreach ($records as $idx => $record) {
            $filteredRecord = $this->filterRecordForUserRecursive($record, $user, $resultCache);
            if (!$filteredRecord) {
                $records->offsetUnset($idx);
            }
        }
        return $records;
    }


    /**
     * Recursively filter a record and its related records.
     *
     * * Records the user is NOT permitted to see are removed completely from graph, becoming NULL
     * * Records the user IS permitted to see have their content filtered appropriately
     *
     * @param Model                     $record
     * @param User                      $user
     * @param Collection|null[]|Model[] $resultCache Indexed by the object's hash id
     * @return Model|null
     * @throws InsufficientHydrationException
     */
    private function filterRecordForUserRecursive(Model $record, User $user, Collection $resultCache): ?Model
    {
        // Base case 1: Already filtered
        $objectHash = \spl_object_hash($record);
        if ($resultCache->keyExists($objectHash)) {
            return $resultCache[$objectHash];
        }

        // Base case 2: User does not have access to this record
        $accessLevel = $record->getAccessLevel($user);
        if (Access\Mapper::ACCESS_DENY === $accessLevel) {
            $resultCache[$objectHash] = null;
            return null;
        }

        // Recursive case: User has some access, censor content and relationships.
        // Add item to our cache to prevent infinite recursion occurring on cyclic relationships
        $resultCache[$objectHash] = $record;

        // Only has public access? Strip the private body and files
        if (Access\Mapper::ACCESS_PUBLIC === $accessLevel) {
            $record->setBodyPrivate("");
            $record->setAttachments($record->getAttachments()->filter(function (Attachment\Model $attachment) {
                return $attachment->isPublic();
            }));
        }

        // And filter the comments on the record
        $this->filterCommentsForUser($record->getComments(), $user);

        // Set the 'updatedAtDisplay' value now that we have all of the comments
        // TODO - This does not belong in this class. This is a presentation concern, not a filtering concern.
        foreach ($record->getComments() as $comment) {
            if ($comment->getUpdatedAt()->gt($record->getUpdatedAtDisplay())) {
                // Found a newer comment, update the record with the date/time, and author of the comment
                $record->setUpdatedAtDisplay($comment->getUpdatedAt());
                $record->setModifiedByDisplay($comment->getOwner());
            }
        }

        // Filter out related records, too
        $filteredRelatedRecords = $record->getRelatedRecords()->emptyClone();
        foreach ($record->getRelatedRecords() as $idx => $relatedRecord) {
            $filteredRelatedRecord = $this->filterRecordForUserRecursive($relatedRecord, $user, $resultCache);
            if ($filteredRelatedRecord) {
                $filteredRelatedRecords[$idx] = $filteredRelatedRecord;
            }
        }
        $record->setRelatedRecords($filteredRelatedRecords);

        return $record;
    }


    /**
     * Removes comments that the specified user is not permitted to view
     *
     * @param Collection|Model[] $comments A list of pastoral comments
     * @param User               $user     The user who is trying to view them
     *
     * @throws InsufficientHydrationException
     */
    private function filterCommentsForUser(Collection $comments, User $user): void
    {
        foreach ($comments as $idx => $comment) {
            $filteredComment = $this->filterCommentForUser($comment, $user);
            if (!$filteredComment instanceof Model) {
                $comments->offsetUnset($idx);
            }
        }
    }


    /**
     * Filter records from the collection if the current user doesn't have access to them.
     * If the user is not in the audience and is not the owner, they can't see the record.
     *
     * @param Comment\Model $comment A pastoral comment
     * @param User          $user    The user who is trying to view them
     *
     * @return Model|null
     * @throws InsufficientHydrationException
     */
    private function filterCommentForUser(Comment\Model $comment, User $user): ?Comment\Model
    {
        // Pastoral moderators get all comments
        if ($user->getAccessList()->filterBy('service', UserAccess::SERVICE_PASTORAL)->count() > 0) {
            return $comment;
        }

        // Record owner and Comment owner can see their comments
        $userId = (int)$user->getId();
        if ($userId === (int)$comment->getOwner()->getId() || $userId === (int)$comment->getPastoral()->getOwner()->getId()) {
            return $comment;
        }

        switch ($comment->getPastoral()->getAccessLevel($user)) {
            case Access\Mapper::ACCESS_PRIVATE:
                // User's with private access can see all comments
                return $comment;
            case Access\Mapper::ACCESS_PUBLIC:
                // User's with public access can only see public comments
                return $comment->isPublic() ? $comment : null;
            case Access\Mapper::ACCESS_DENY:
            default:
                // All other users should not see the comment
                return null;
        }
    }
}
