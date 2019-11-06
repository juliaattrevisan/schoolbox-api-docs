<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Pastoral;

use Schoolbox\Mapper\Exception\InsufficientHydrationException;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;
use Schoolbox\Pastoral\Group\Member\Mapper as PastoralGroupMemberMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;
use Schoolbox\Collection;
use function spl_object_hash;

/**
 * Responsible for stripping the content/relationships from a pastoral record that a
 * specific user does not have sufficient permission to access.
 * The original pastoral model passed in will not be changed.
 */
class AccessLevelCensor
{

    /** @var PastoralGroupMemberMapper $pastoralGroupMemberMapper */
    private $pastoralGroupMemberMapper;

    /**
     * Gets PastoralGroupMemberMapper
     * @return PastoralGroupMemberMapper
     */
    public function getPastoralGroupMemberMapper(): PastoralGroupMemberMapper
    {
        if ($this->pastoralGroupMemberMapper === null) {
            $this->pastoralGroupMemberMapper = PastoralGroupMemberMapper::getInstance();
        }
        return $this->pastoralGroupMemberMapper;
    }

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
        $resultCache     = new Collection();
        $filteredRecords = Model::createCollection();
        foreach ($records as $idx => $record) {
            $filteredRecord = $this->filterRecordForUserRecursive($record, $user, $resultCache);
            if ($filteredRecord) {
                $filteredRecords->add($filteredRecord);
            }
        }
        return $filteredRecords;
    }


    /**
     * Filters record and record content that the specified user is not permitted to view.
     *
     * * Records the user is NOT permitted to see are removed completely from graph, becoming NULL
     * * Records the user IS permitted to see have their content filtered appropriately
     *
     * Public-facing recursive driver.
     *
     * @param Model $record A pastoral records
     * @param User  $user   The user who is trying to view them
     * @return null|Model
     * @throws InsufficientHydrationException
     */
    public function filterRecordForUser(Model $record, User $user): ?Model
    {
        $resultCache = new Collection();
        return $this->filterRecordForUserRecursive($record, $user, $resultCache);
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
        $objectHash = spl_object_hash($record);
        if ($resultCache->keyExists($objectHash)) {
            return $resultCache[$objectHash];
        }

        $filteredRecord = clone $record;

        // Base case 2: User does not have access to this record
        $accessLevel = $this->getAccessLevel($filteredRecord, $user);
        if (Access\Mapper::ACCESS_DENY === $accessLevel) {
            $resultCache[$objectHash] = null;
            return null;
        }

        // Recursive case: User has some access, censor content and relationships.
        // Add item to our cache to prevent infinite recursion occurring on cyclic relationships
        $resultCache[$objectHash] = $filteredRecord;

        // Only has public access? Strip the private body and files
        if (Access\Mapper::ACCESS_PUBLIC === $accessLevel) {
            $filteredRecord->setBodyPrivate(null);
            $filteredRecord->setAttachments($filteredRecord->getAttachments()->filter(function (Attachment\Model $attachment) {
                return $attachment->isPublic();
            }));
        }

        // And filter the comments on the record
        $filteredComments = $filteredRecord->getComments()->emptyClone();
        foreach ($filteredRecord->getComments() as $comment) {
            $clonedComment   = clone $comment;
            $filteredComment = $this->filterCommentForUser($clonedComment, $user);
            if ($filteredComment instanceof \Schoolbox\Pastoral\Comment\Model) {
                $filteredComments->add($filteredComment);
                // Set the 'updatedAtDisplay' value now that we have all of the comments
                // TODO - This does not belong in this class. This is a presentation concern, not a filtering concern.
                if ($filteredComment->getUpdatedAt()->gt($filteredRecord->getUpdatedAtDisplay())) {
                    // Found a newer comment, update the record with the date/time, and author of the comment
                    $filteredRecord->setUpdatedAtDisplay($filteredComment->getUpdatedAt());
                    $filteredRecord->setModifiedByDisplay($filteredComment->getOwner());
                }
            }
        }
        $filteredRecord->setComments($filteredComments);

        // Filter out related records, too
        $filteredRelatedRecords = $filteredRecord->getRelatedRecords()->emptyClone();
        foreach ($filteredRecord->getRelatedRecords() as $idx => $relatedRecord) {
            $filteredRelatedRecord = $this->filterRecordForUserRecursive($relatedRecord, $user, $resultCache);
            if ($filteredRelatedRecord) {
                $filteredRelatedRecords[$idx] = $filteredRelatedRecord;
            }
        }
        $filteredRecord->setRelatedRecords($filteredRelatedRecords);

        return $filteredRecord;
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
        if ($this->isPastoralModerator($user)) {
            return $comment;
        }

        // Record owner and Comment owner can see their comments
        $userId = $user->getId();
        if ($userId === $comment->getOwner()->getId() || $userId === $comment->getPastoral()->getOwner()->getId()) {
            return $comment;
        }

        switch ($this->getAccessLevel($comment->getPastoral(), $user)) {
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

    /**
     * Return the specified user's access level to this record
     *
     * Order of preference:
     * 1. Pastoral Moderators and the record owner always have PRIVATE access
     * 2. If any access rule applies DENY access, then access level is DENY
     * 3. If no rules apply, then access level is DENY
     * 4. Rules apply, and none apply DENY, return  max access level
     *
     * @param Model $record
     * @param User  $user Must have services, role, and pastoral group memberships hydrated
     * @return int
     */
    public function getAccessLevel(Model $record, User $user): int
    {
        // Owners and moderators get private access regardless of any other permission set
        if ($this->isPastoralModerator($user) || (int)$user->getId() === (int)$record->getOwner()->getId()) {
            return PastoralAccessMapper::ACCESS_PRIVATE;
        }

        // Hydrate group membership
        if ($user->getPastoralGroupsMemberships() === null) {
            $user->setPastoralGroupsMemberships(
                $this->getPastoralGroupMemberMapper()->findByColumn('user_id', $user->getId())
            );
        }

        // Otherwise start with deny access, and work our way up the permission levels
        $accessLevel = PastoralAccessMapper::ACCESS_DENY;

        foreach ($record->getAccess() as $access) {
            $access->setPastoral($record);
            $accessLevelCurrent = $access->getAccessLevel($user);

            if ($accessLevelCurrent === null) {
                // The access rule didn't apply to this user, continue to the next one
                continue;
            }

            if ($accessLevelCurrent === PastoralAccessMapper::ACCESS_DENY) {
                return PastoralAccessMapper::ACCESS_DENY;
            }

            // We want the highest access level
            $accessLevel = max($accessLevel, $accessLevelCurrent);
        }

        return $accessLevel;
    }


    /**
     * Checks whether the user has pastoral moderator permission
     * @param User $user
     * @return bool
     */
    public function isPastoralModerator(User $user): bool
    {
        return null !== $user->getAccessList()->getBy('service', UserAccess::SERVICE_PASTORAL);
    }
}
