<?php

/**
 * Calendar_Layout_Full_Abstract
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Authorizer\Authorizer;
use Aura\Router\Router;
use Calendar\Api\ModuleChecker;
use Schoolbox\User;
use Schoolbox\UserMapper;

abstract class Calendar_Layout_Full_Abstract
{
    /**
     * Actual calendar API instance, used as a source of events.
     *
     * @var Calendar_Api_EventType_Interface
     */
    protected $api;


    /**
     * The subject of this calendar.
     *
     * @var Schoolbox\User $user
     */
    protected $user;


    /**
     * Who is currently viewing the calendar?
     * (as opposed to the subject of the calendar)
     *
     * @var Schoolbox\User $viewer
     */
    protected $viewer;


    /**
     * The source of information on whether a module is enabled.
     *
     * Used to determine which event sources are available.
     *
     * @var ModuleChecker
     */
    private $moduleChecker;


    /**
     * Authorizer, used for determining whether particular links in calendar
     * events are generated.
     *
     * @var Authorizer
     */
    protected $authorizer;


    /**
     * Router, used for generating links in calendar events.
     *
     * @var Router
     */
    protected $router;


    /**
     * Constructor
     *
     * @param Authorizer           $authorizer
     * @param Router               $router
     * @param User                 $user
     * @param User                 $viewer
     * @param Calendar_Api_Factory $factory
     * @param ModuleChecker        $moduleChecker
     * @param int|null             $folderId
     */
    public function __construct(
        Authorizer $authorizer,
        Router $router,
        User $user,
        User $viewer,
        Calendar_Api_Factory $factory,
        ModuleChecker $moduleChecker,
        ?int $folderId = null
    ) {
        $this->api           = isset($folderId)
            ? $factory->produceForFolderId($folderId, $user, $viewer)
            : $factory->produceForUser($user, $viewer);
        $this->moduleChecker = $moduleChecker;
        $this->user          = $user;
        $this->viewer        = $viewer;
        $this->authorizer    = $authorizer;
        $this->router        = $router;
    }

    /**
     * Does the current user have permission to view this calendar page?
     *
     * @param int            $userId
     * @param Schoolbox\User $currentUser
     * @param Group          $groupHandler
     * @throws Exception if user doesn't have permission to view calendar.
     *                   The exception's code matches a Schoolbox error code.
     * @return bool
     */
    public static function permissionCheck($userId, Schoolbox\User $currentUser, Group $groupHandler): bool
    {
        if ($currentUser->getId() != $userId) {
            try {
                UserMapper::getInstance()->findById($userId);
            } catch (Exception $e) {
                throw new Exception('User does not exist', 31, $e);
            }
            if (
                !$currentUser->getSuperuser() &&
                !$currentUser->getRole()->isStaff() &&
                !$groupHandler->isParentOfChild($userId)
            ) {
                throw new Exception('Current user does not have permission to view calendar for this user', 125);
            }
        }
        return true;
    }

    /**
     * @return void
     */
    abstract protected function render(): void;
}
