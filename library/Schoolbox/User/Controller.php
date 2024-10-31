<?php

/**
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 */

namespace Schoolbox\User;

use Alaress\Session\ImpersonationCheck;
use Alaress\Web\Controller\Base;
use GuzzleHttp\ClientInterface;
use Schoolbox\Oidc\Server\BackChannelLogout\RequestFactory;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\User;
use User\Logger;
use External_User;
use Ramsey\Uuid\Uuid;
use Schoolbox\Oidc\Server\RelyingParty\Mapper as RelyingPartyMapper;
use Schoolbox\Oidc\Server\BackChannelLogout;
use Schoolbox\Oidc\Server\Controller as OidcServerController;

class Controller extends Base
{
    /**
     * @var UserAccessMapper
     */
    protected $userAccessMapper;

    /** @var Logger\Mapper */
    private $assumeUserLogMapper;

    /** @var RelyingPartyMapper */
    private $relyingPartyMapper;
    private $requestFactory;

    private $httpClient;

    public function __construct(
        UserAccessMapper $userAccessMapper,
        Logger\Mapper $assumeUserLogMapper,
        RelyingPartyMapper $relyingPartyMapper,
        RequestFactory $requestFactory,
        ClientInterface $httpClient,
    ) {
        $this->userAccessMapper    = $userAccessMapper;
        $this->assumeUserLogMapper = $assumeUserLogMapper;
        $this->relyingPartyMapper  = $relyingPartyMapper;
        $this->requestFactory      = $requestFactory;
        $this->httpClient          = $httpClient;
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
        $oidcServerClients = $this->session
            ->get(OidcServerController::SESSION_OIDC_SERVER_CLIENTS);

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

        $this->logoutOIDCSessions(
            $this->relyingPartyMapper,
            $this->requestFactory,
            $this->httpClient,
            $this->getUser(),
            $oidcServerClients,
        );

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
        $oidcServerClients = $this->session
            ->get(OidcServerController::SESSION_OIDC_SERVER_CLIENTS);
        if ($user instanceof User) {
            $session = new \Session();
            if (!$session->create($user->getUsername())) {
                $session->destroy();
            }
            $this->logoutOIDCSessions(
                $this->relyingPartyMapper,
                $this->requestFactory,
                $this->httpClient,
                $this->getUser(),
                $oidcServerClients
            );
        }
        $this->response = $this->redirectFound('/');
    }

    /**
     * Sending request to log out of the oAuth session
     *
     * @param RelyingPartyMapper $relyingPartyMapper
     * @param RequestFactory     $logoutFactory
     * @param ClientInterface    $httpClient
     * @param User               $initialUser
     * @param array|null         $oidcServerClients
     * @return void
     */
    private function logoutOIDCSessions(
        RelyingPartyMapper $relyingPartyMapper,
        RequestFactory $logoutFactory,
        ClientInterface $httpClient,
        User $initialUser,
        ?array $oidcServerClients,
    ): void {
        // This is similar to what's implemented in www/logout.php when user logs out
        $backChannelRequests = [];
        foreach ($oidcServerClients ?? [] as $clientId) {
            // Does this client support logout?
            // If so, craft a logout token and send it
            $client = $relyingPartyMapper->findByClientId($clientId);
            if ($client?->getBackChannelLogoutUri()) {
                // Create logout factory and client on demand only
                $request = $logoutFactory->build(
                    $initialUser,
                    $client,
                    Uuid::uuid4()->toString()
                );
                $logoutClient ??= new BackChannelLogout\Client($httpClient);
                $backChannelRequests[$clientId] = $logoutClient->send($request);
            }

            // Wait for any back-channel requests to complete
            if (!empty($backChannelRequests)) {
                \GuzzleHttp\Promise\Utils::settle($backChannelRequests)->wait();
            }
        }
    }
}
