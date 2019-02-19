<?php
/**
 * Alaress\Image\Cache\Portrait
 *
 * Load user's portraits from the external database.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Image\Cache;

use External_User;
use Group;
use Schoolbox\Config\Loader;
use Schoolbox\IConfigLoader;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Portrait extends Storage
{

    /** @var UserMapper */
    private $userMapper;

    /** @var User */
    private $sessionUser;

    /** @var IConfigLoader */
    private $config;


    /**
     * Portrait constructor.
     *
     * @param User   $user
     * @param string $key
     * @param User   $sessionUser
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function __construct(User $user, string $key, User $sessionUser)
    {
        $this->config    = new Loader();
        $this->sessionUser = $sessionUser;
        $this->userMapper  = UserMapper::getInstance();

        $externalHandler = new External_User();

        /* If Load Photos From SIS is on, yank down the latest version, then cache it in brownhole
         * Otherwise try to use the one assigned to user, which lives in storage
         */
        if ($this->config->get('photo_sis_import')) {
            $portrait = $externalHandler->getPortrait($user->getExternalId());
        } else {
            $portrait = $user->getPortrait();
        }

        /* Then load it from this cache in the normal way
         */
        try {
            parent::__construct($portrait, $key);
        } catch (\League\Flysystem\FilesystemNotFoundException $e) {
            throw new \InvalidArgumentException('Portrait not found');
        }

        if (!$this->canViewImage($user->getExternalId())) {
            throw new \RuntimeException('Cannot view image');
        }
    }


    /**
     * Determine whether a portrait can be seen or not
     *
     * @param string $externalId
     * @return bool
     */
    private function canViewImage(string $externalId): bool
    {
        // TODO kill it! kill it with fire!
        $group = new Group();

        /* Staff members can always see all portraits
           */
        if ($this->sessionUser->getRole()->isStaff()) {
            return true;
        }

        // Get all users with a synergy ID, and test them against policy.

        /** @var User[] $userList */
        $userList = $this->userMapper->findByColumn('synergy_id', $externalId);

        foreach ($userList as $user) {
            /* Everyone can always see their own face
             */
            $userId = $user->getId();
            if ($this->sessionUser->getId() === $userId) {
                return true;
            }

            // Student photos:
            // always visible by their parents
            // visible by other students if photos are not protected.
            if ($group->isParentOfChild($userId)) {
                return true;
            }

            if ($group->isStudent($userId) && !$this->config->get('protect_student_photo') && $this->sessionUser->getRole()->isStudent()) {
                return true;
            }

            // Staff photos:
            // visible by students and parents, if staff photos aren't protected.
            if ($group->isStaff($userId)) {

                /**
                 * Options in protect_teacher_photo are;
                 *  0 => Staff, parents and students can see photo
                 *  1 => No one can see photo
                 *  2 => Staff and Parents can see photo
                 */
                switch ($this->config->get('protect_teacher_photo')) {
                    case 0:
                        return $this->sessionUser->getRole()->isStudent() || $this->sessionUser->getRole()->isParent();
                    case 1:
                        return false;
                    case 2:
                        return $this->sessionUser->getRole()->isParent();
                    default:
                        return false;
                }
            }
        }

        return false;
    }
}
