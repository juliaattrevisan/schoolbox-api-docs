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
use Schoolbox\User;
use Schoolbox\UserMapper;

class Portrait extends Storage
{
    private const PROTECT_TEACHER_PHOTOS_FROM_NONE     = 0;
    private const PROTECT_TEACHER_PHOTOS_FROM_ALL      = 1;
    private const PROTECT_TEACHER_PHOTOS_FROM_STUDENTS = 2;

    /** @var UserMapper */
    private $userMapper;

    /** @var User */
    private $sessionUser;

    /**
     * @var bool
     */
    private $protectStudentPhotos;

    /**
     * @var int
     */
    private $protectTeacherPhotos;

    /**
     * Constructor
     *
     * @param User   $user
     * @param string $key
     * @param User   $sessionUser
     * @param bool   $loadPhotosFromSis
     * @param bool   $protectStudentPhotos
     * @param int    $protectTeacherPhotos
     */
    public function __construct(
        User $user,
        string $key,
        User $sessionUser,
        bool $loadPhotosFromSis,
        bool $protectStudentPhotos,
        int $protectTeacherPhotos
    ) {
        // TODO inject user mapper and external user
        $this->sessionUser          = $sessionUser;
        $this->userMapper           = UserMapper::getInstance();
        $this->protectStudentPhotos = $protectStudentPhotos;
        $this->protectTeacherPhotos = $protectTeacherPhotos;

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
            throw new \RuntimeException('No portrait image selected');
        }

        /* Otherwise load it from this cache in the normal way
         */
        try {
            parent::__construct($portrait, $key);
        } catch (\League\Flysystem\FilesystemNotFoundException $e) {
            throw new \InvalidArgumentException('Portrait not found');
        }

        if (!$this->canViewImage($user)) {
            throw new \RuntimeException('Cannot view image');
        }
    }


    /**
     * Determine whether a portrait can be seen or not
     *
     * @param User $user
     * @return bool
     */
    private function canViewImage(User $user): bool
    {
        $externalId = $user->getExternalId();

        // TODO kill it! kill it with fire!
        $group = new Group();

        /* Staff members can always see all portraits
         */
        if ($this->sessionUser->getRole()->isStaff()) {
            return true;
        }

        // Get all users with a synergy ID, and test them against policy.

        /** @var User[] $userList */
        $users = $this->userMapper->findByColumn('synergy_id', $externalId);

        foreach ($users as $currentUser) {

            /* Everyone can always see their own face
             */
            $userId = $currentUser->getId();

            if ($this->sessionUser->getId() === $userId) {
                return true;
            }

            // Student photos:
            // always visible by their parents
            // visible by other students if photos are not protected.
            if ($group->isParentOfChild($userId)) {
                return true;
            }

            if (!$this->protectStudentPhotos && $group->isStudent($userId) && $this->sessionUser->getRole()->isStudent()) {
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
                switch ($this->protectTeacherPhotos) {
                    case self::PROTECT_TEACHER_PHOTOS_FROM_NONE:
                        return $this->sessionUser->getRole()->isStudent() || $this->sessionUser->getRole()->isParent();
                    case self::PROTECT_TEACHER_PHOTOS_FROM_STUDENTS:
                        return $this->sessionUser->getRole()->isParent();
                    case self::PROTECT_TEACHER_PHOTOS_FROM_ALL:
                    default:
                        return false;
                }
            }
        }

        return false;
    }
}
