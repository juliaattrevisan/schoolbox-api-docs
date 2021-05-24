<?php

/**
 * @author      James Leckie <james.leckie@schoolbox.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Authorizer\Rule;

use Alaress\Authorizer\Failure;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\User;
use InvalidArgumentException;

/**
 * This class is used to authorize users to access features like the markbook.  It checks that the user is not a learner
 * first, then checks if they are staff or have WRITE access to the folder.
 * @package Schoolbox\Learning\Authorizer\Rule
 */
class IsInstructor extends AbstractParticipationRule
{

    /**
     * @var Access
     */
    private $access;


    /**
     * Method used for locating the folder.
     *
     * By default, the folder will be in the "folder" key of params.
     *
     * @var callable|null
     */
    private $folderLocator;


    /**
     * Constructor
     *
     * @param Access        $access
     * @param callable|null $folderLocator
     */
    public function __construct(Access $access, callable $folderLocator = null)
    {
        $this->access        = $access;
        $this->folderLocator = $folderLocator;
    }


    /**
     * @param array     $params
     * @param User|null $user
     * @return bool
     * @throws Failure\Unauthorized If user is not an instructor of this class
     * @throws InvalidArgumentException If unable to locate an User instance in route params
     */
    public function authorize(array $params = [], User $user = null)
    {

        $folder = is_callable($this->folderLocator)
            ? call_user_func($this->folderLocator, $params)
            : $this->defaultFolderLocator($params);

        if (!$user) {
            throw new InvalidArgumentException('No user supplied to authorize');
        }

        /* If the user only has READ access via group permissions, they are not permitted as an instructor */
        foreach ($user->getAccessList() as $access) {
            if (
                $access->getService() == UserAccess::SERVICE_HOMEPAGE
                && $access->getAccessLevel() == UserAccess::ACCESS_READ
                && $access->getServiceIdentifier() == $folder->getId()
            ) {
                throw new Failure\Unauthorized();
            }
        }

        if ($user->getRole()->isStaff() || $this->access->isInstructor($user, $folder)) {
            return true;
        }


        throw new Failure\Unauthorized();
    }


    /**
     * Default folder locator
     *
     * @param array $params
     * @return FolderModel
     */
    private function defaultFolderLocator(array $params): FolderModel
    {
        return $params['folder'];
    }
}
