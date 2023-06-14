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
    protected Calendar_Api_EventType_Interface $api;


    /**
     * The subject of this calendar.
     *
     * @var Schoolbox\User $user
     */
    protected Schoolbox\User $user;


    /**
     * Who is currently viewing the calendar?
     * (as opposed to the subject of the calendar)
     *
     * @var Schoolbox\User $viewer
     */
    protected Schoolbox\User $viewer;


    /**
     * The source of information on whether a module is enabled.
     *
     * Used to determine which event sources are available.
     *
     * @var ModuleChecker
     */
    private ModuleChecker $moduleChecker;


    /**
     * Authorizer, used for determining whether particular links in calendar
     * events are generated.
     *
     * @var Authorizer
     */
    protected Authorizer $authorizer;


    /**
     * Router, used for generating links in calendar events.
     *
     * @var Router
     */
    protected Router $router;


    /**
     * A set of luEventType ids by which to restrict the result set
     *
     * @var array|null
     */
    protected ?array $allowedEventTypes = null;


    /**
     * Constructor
     *
     * @param Authorizer                       $authorizer
     * @param Router                           $router
     * @param User                             $user
     * @param User                             $viewer
     * @param Calendar_Api_EventType_Interface $api
     * @param ModuleChecker                    $moduleChecker
     * @param array|null                       $allowedEventTypes
     */
    public function __construct(
        Authorizer $authorizer,
        Router $router,
        User $user,
        User $viewer,
        Calendar_Api_EventType_Interface $api,
        ModuleChecker $moduleChecker,
        ?array $allowedEventTypes = null
    ) {
        $this->api               = $api;
        $this->moduleChecker     = $moduleChecker;
        $this->user              = $user;
        $this->viewer            = $viewer;
        $this->authorizer        = $authorizer;
        $this->router            = $router;
        $this->allowedEventTypes = $allowedEventTypes;
    }


    /**
     * Does the current user have permission to view this calendar page?
     *
     * @param Schoolbox\User $viewee
     * @param Schoolbox\User $currentUser
     * @return bool
     * @throws Exception if user doesn't have permission to view calendar.
     *                   The exception's code matches a Schoolbox error code.
     */
    public static function permissionCheck(Schoolbox\User $viewee, Schoolbox\User $currentUser): bool
    {
        if ($currentUser->getId() !== $viewee->getId()) {
            if (
                !$currentUser->getSuperuser() &&
                !$currentUser->getRole()->isStaff() &&
                !$currentUser->isParentOf($viewee)
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
