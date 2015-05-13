<?php
/**
 * Alaress\Image\Cache\Portrait
 * Load user's portraits from the external database.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Image\Cache;

use Exception_Schoolbox;
use Exception_Schoolbox_AccessDenied;
use Exception_Schoolbox_NotFound;
use External_User;
use Group;
use Schoolbox\Config\Loader;
use Schoolbox\File\Exception;
use Schoolbox\IConfigLoader;
use Schoolbox\User;
use Schoolbox\UserMapper;
use League\Flysystem as Fly;

class Portrait extends BrokenFS
{

    /** @var int */
    private $externalUserId = -1;

    /** @var External_User */
    private $externalHandler = null;

    /** @var UserMapper */
    private $userMapper;

    /** @var User */
    private $sessionUser;

    /** @var IConfigLoader */
    private $conf;


    /**
     * @param int    $externalId
     * @param string $key
     * @param User   $sessionUser
     * @throws Exception_Schoolbox
     */
    public function __construct($externalId, $key, User $sessionUser)
    {
        $this->setConfig(new Loader());
        $this->setSessionUser($sessionUser);
        $this->setExternalHandler(new External_User());
        $this->setUserMapper(UserMapper::getInstance());
        $this->setExternalUserId($externalId);

        $portraitPath = $this->getExternalHandler()->getPortrait($this->getExternalUserId());

        // truncate the dir for Flysystem
        $portraitPath = str_replace($this->getConfig()->get('portrait_dir'), '', $portraitPath);

        try {
            parent::__construct($this->getConfig()->get('portrait_dir'), $portraitPath, $key);
        } catch (Exception $e) {
            throw new Exception_Schoolbox_NotFound('Portrait not found');
        }

        $this->purgeOldTransformations();

        if (!$this->canViewImage()) {
            throw new Exception_Schoolbox_AccessDenied('Cannot view image');
        }
    }

    /**
     * Purges transformations that are older than the portrait in $portraitPath.
     */
    public function purgeOldTransformations()
    {
        if (!$this->resource->exists()) {
            return;
        }

        $portraitAge = $this->resource->getTimestamp();

        $cachePath = $this->loadCache()->getPath();
        $cacheDir  = pathinfo($cachePath, PATHINFO_DIRNAME);

        try {
            /** @var Fly\Directory $dir */
            $dir = $this->cacheFs->get($cacheDir);

            foreach ($dir->getContents() as $meta) {
                $modTime = $this->cacheFs->getTimestamp($meta['path']);
                if ($modTime < $portraitAge) {
                    $this->cacheFs->delete($meta['path']);
                }
            }
        } catch (Fly\FileNotFoundException $e) {
            // this simply means the cache directory doesn't exist
        }
    }

    /**
     * Determine whether a portrait can be seen or not
     *
     * @return bool
     */
    private function canViewImage()
    {
        // TODO kill it! kill it with fire!
        $group = new Group();

        /* Staff members can always see all portraits
           */
        if ($group->isStaff()) {
            return true;
        }

        // Get all users with a synergy ID, and test them against policy.

        /** @var User[] $userList */
        $userList = $this->getUserMapper()->findByColumn('synergy_id', $this->getExternalUserId());

        foreach ($userList as $user) {
            /* Everyone can always see their own face
             */
            $userId = $user->getId();
            if ($this->getSessionUser()->getId() == $userId) {
                return true;
            }

            // Student photos:
            // always visible by their parents
            // visible by other students if photos are not protected.
            if ($group->isParentOfChild($userId)) {
                return true;
            }
            if ($group->isStudent($userId)) {
                if (!$this->getConfig()->get('protect_student_photo')
                    && $group->isStudent()
                ) {
                    return true;
                }
            }

            // Staff photos:
            // visible by students and parents, if staff photos aren't protected.
            if ($group->isStaff($userId)) {
                if (!$this->getConfig()->get('protect_teacher_photo')
                    && ($group->isStudent() || $group->isParent())
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Set external handler
     *
     * @param External_User $externalHandler
     */
    private function setExternalHandler($externalHandler)
    {
        $this->externalHandler = $externalHandler;
    }

    /**
     * Get external handler
     *
     * @return External_User
     */
    private function getExternalHandler()
    {
        return $this->externalHandler;
    }


    /**
     * @return UserMapper
     */
    private function getUserMapper()
    {
        return $this->userMapper;
    }


    /**
     * @param UserMapper $userMapper
     * @return self
     */
    private function setUserMapper($userMapper)
    {
        $this->userMapper = $userMapper;
        return $this;
    }


    /**
     * @return User
     */
    private function getSessionUser()
    {
        return $this->sessionUser;
    }


    /**
     * @param User $sessionUser
     * @return self
     */
    private function setSessionUser($sessionUser)
    {
        $this->sessionUser = $sessionUser;
        return $this;
    }


    /**
     * @return IConfigLoader
     */
    private function getConfig()
    {
        return $this->conf;
    }

    /**
     * @param IConfigLoader $conf
     * @return self
     */
    private function setConfig($conf)
    {
        $this->conf = $conf;
        return $this;
    }

    /**
     * Get external id
     *
     * @return mixed
     */
    private function getExternalUserId()
    {
        return $this->externalUserId;
    }

    /**
     * Set external user id
     *
     * @param $externalUserId
     */
    private function setExternalUserId($externalUserId)
    {
        $this->externalUserId = $externalUserId;
    }
}
