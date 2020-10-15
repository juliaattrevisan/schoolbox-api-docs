<?php
/**
 * Alaress\Image\Cache\Portrait
 *
 * Load user's portraits from the external database.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Image\Cache;

use External_User;
use Group;
use InvalidArgumentException;
use RuntimeException;
use Schoolbox\Resource\Storage;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Portrait
{
    private const PROTECT_TEACHER_PHOTOS_FROM_NONE     = 0;
    private const PROTECT_TEACHER_PHOTOS_FROM_ALL      = 1;
    private const PROTECT_TEACHER_PHOTOS_FROM_STUDENTS = 2;

    /**
     * @param User $user
     * @param User $sessionUser
     * @param bool $loadPhotosFromSis
     * @param bool $protectStudentPhotos
     * @param int  $protectTeacherPhotos
     * @return Storage\Model
     * @throws InvalidArgumentException If user does not have a portrait
     * @throws RuntimeException  If session user is not permitted to view portrait
     */
    public static function getStorageModel(
        User $user,
        User $sessionUser,
        bool $loadPhotosFromSis,
        bool $protectStudentPhotos,
        int $protectTeacherPhotos
    ): Storage\Model {
        // TODO inject user mapper and external user
        $externalHandler = new External_User();

        /* If Load Photos From SIS is on, yank down the latest version, then cache it in brownhole
         * Otherwise try to use the one assigned to user, which lives in storage
         */
        if ($loadPhotosFromSis) {
            $portrait = $externalHandler->getPortrait($user);
        } else {
            $portrait = $user->getPortrait();
        }

        /* If this is null, the picture was unlinked - that is, removed in the sis
         * Remove it in schoolbox too, by displaying the Grey Man
         */
        if (null === $portrait) {
            throw new InvalidArgumentException('No portrait image selected');
        }

        if (!self::canViewImage($sessionUser, $user, UserMapper::getInstance(), $protectStudentPhotos, $protectTeacherPhotos)) {
            throw new RuntimeException('Cannot view image');
        }

        return $portrait;
    }


    /**
     * Determine whether a portrait can be seen or not
     *
     * @param User       $sessionUser
     * @param User       $user
     * @param UserMapper $userMapper
     * @param bool       $protectStudentPhotos
     * @param int        $protectTeacherPhotos
     * @return bool
     */
    private static function canViewImage(
        User $sessionUser,
        User $user,
        UserMapper $userMapper,
        bool $protectStudentPhotos,
        int $protectTeacherPhotos
    ): bool {
        $externalId = $user->getExternalId();

        // TODO kill it! kill it with fire!
        $group = new Group();

        /* Staff members can always see all portraits
         */
        if ($sessionUser->getRole()->isStaff()) {
            return true;
        }

        // Get all users with a synergy ID, and test them against policy.

        $users = $userMapper->findByColumn('synergy_id', $externalId);

        foreach ($users as $currentUser) {
            /* Everyone can always see their own face
             */
            $userId = $currentUser->getId();

            if ($sessionUser->getId() === $userId) {
                return true;
            }

            // Student photos:
            // always visible by their parents
            // visible by other students if photos are not protected.
            if ($group->isParentOfChild($userId)) {
                return true;
            }

            if (!$protectStudentPhotos && $group->isStudent($userId) && $sessionUser->getRole()->isStudent()) {
                return true;
            }

            // Staff photos:
            // visible by students and parents, if staff photos aren't protected.
            if ($group->isStaff($userId)) {

                /**
                 * Options in protect_teacher_photo are;
                 *  PROTECT_TEACHER_PHOTOS_FROM_NONE     => Staff, parents and students can see photo
                 *  PROTECT_TEACHER_PHOTOS_FROM_ALL      => No one can see photo
                 *  PROTECT_TEACHER_PHOTOS_FROM_STUDENTS => Staff and Parents can see photo
                 */
                switch ($protectTeacherPhotos) {
                    case self::PROTECT_TEACHER_PHOTOS_FROM_NONE:
                        return $sessionUser->getRole()->isStudent() || $sessionUser->getRole()->isParent();
                    case self::PROTECT_TEACHER_PHOTOS_FROM_STUDENTS:
                        return $sessionUser->getRole()->isParent();
                    case self::PROTECT_TEACHER_PHOTOS_FROM_ALL:
                    default:
                        return false;
                }
            }
        }

        return false;
    }
}
