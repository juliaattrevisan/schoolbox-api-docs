<?php

/**
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 */

namespace Schoolbox\User;

use Alaress\Session\ImpersonationCheck;
use Alaress\Web\Controller\Base;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\User;
use User\Logger;
use External_User;

class Controller extends Base
{
    /**
     * @var UserAccessMapper
     */
    protected $userAccessMapper;

    /** @var Logger\Mapper */
    private $assumeUserLogMapper;

    public function __construct(
        UserAccessMapper $userAccessMapper,
        Logger\Mapper $assumeUserLogMapper
    ) {
        $this->userAccessMapper    = $userAccessMapper;
        $this->assumeUserLogMapper = $assumeUserLogMapper;
    }


    /**
     * Function to transfer to another user (impersonate), stores your previous session for later return
     *
     * @api /user/switch/{userId}
     * @param User $user
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function impersonate(User $user)
    {
        $currentUser    = $this->getUser();
        $initialUser    = $this->session->get(ImpersonationCheck::SESSION_KEY_INITIAL_USER);
        $sessionHandler = new \Session();

        if ($sessionHandler->create($user->getUsername())) {
            // Log the user which began impersonation
            $this->session->set(ImpersonationCheck::SESSION_KEY_INITIAL_USER, $initialUser ?? $currentUser);

            // Log the current user in the previous
            $this->session->set(ImpersonationCheck::SESSION_KEY_PREVIOUS_USER, $currentUser);

            // Who assumed this session? IMO it is always the initial user
            // (the user who first started impersonating)
            /** @var User $initialUser */
            $initialUser = $this->session->get(ImpersonationCheck::SESSION_KEY_INITIAL_USER, $currentUser);

            $logItem = $this->assumeUserLogMapper->createModel()
                ->setUser($initialUser->getUsername())
                ->setAssumed($user->getUsername())
                ->setIp($_SERVER['REMOTE_ADDR']);

            $this->assumeUserLogMapper->save($logItem);
        } else {
            // Recover old session:
            // should do so by creating a new session for previous user, as that
            // is what `recoverSession()` does.
            $sessionHandler->create($currentUser->getUsername());
        }
        $this->response = $this->redirectFound('/');
    }


    /**
     * Backwards-compatible alias for recovering to previous session.
     *
     * @api /user/recoverSession
     */
    public function recoverSession(): void
    {
        $this->recoverSessionPrevious();
    }

    /**
     * Return to your previous session, after impersonation.
     *
     * This is the most recent session you impersonated: this could be different
     * to the initial session, if you have impersonated a user from within
     * another impersonation.
     *
     * @api /user/recoverSession/previous
     */
    public function recoverSessionPrevious(): void
    {
        // Preserve initial user, also previous user = current user so that this
        // session may itself recover its initial and previous user
        $currentUser = $this->getUser();
        $initialUser = $this->session->get(ImpersonationCheck::SESSION_KEY_INITIAL_USER);
        $this->recoverSessionInternal(ImpersonationCheck::SESSION_KEY_PREVIOUS_USER);
        $this->session->set(ImpersonationCheck::SESSION_KEY_INITIAL_USER, $initialUser);
        $this->session->set(ImpersonationCheck::SESSION_KEY_PREVIOUS_USER, $currentUser);
    }

    /**
     * Return to your initial session, after impersonation.
     *
     * This is the session you originally logged in with.
     *
     * @api /user/recoverSession/initial
     */
    public function recoverSessionInitial(): void
    {
        $this->recoverSessionInternal(ImpersonationCheck::SESSION_KEY_INITIAL_USER);
    }

    /**
     * Recover the session found in `$key`.
     *
     * @param string $key
     * @return void
     */
    private function recoverSessionInternal(string $key): void
    {
        $user = $this->session->get($key);
        if ($user instanceof User) {
            $session = new \Session();
            if (!$session->create($user->getUsername())) {
                $session->destroy();
            }
        }
        $this->response = $this->redirectFound('/');
    }
}
