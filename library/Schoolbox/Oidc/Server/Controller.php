<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Oidc\Server;

use Alaress\Web\Controller\Base;
use League\OAuth2\Server;
use OAT\Library\Lti1p3Core\Security\Jwks\Exporter\JwksExporter;
use OAT\Library\Lti1p3Core\Security\Key\KeyChainRepositoryInterface;
use Schoolbox\LTI\KeyChain\RepositoryFactory;

/**
 * Controller for OpenID Connect identity provision.
 *
 * This implements Schoolbox as an identity provider (e.g. a server) for an
 * OpenID relying party.
 */
class Controller extends Base
{
    /**
     * Constructor
     *
     * @paramServer\AuthorizationServer   $authServer The authorization server used to service OIDC auth requests.
     * @param KeyChainRepositoryInterface $keyChains  Repository of keychains: set up for LTI, but usable outside of
     *                                                that context.
     */
    public function __construct(
        private Server\AuthorizationServer $authServer,
        private KeyChainRepositoryInterface $keyChains,
    ) {
    }

    /**
     * Render an authorization page for an OIDC authorization code flow.
     *
     * This displays a form which queries whether the user authorizes the
     * relying party to access the data it requests.
     *
     * @api GET /oidc/authorize
     * @since 24.0
     *
     * @return void
     */
    public function getAuthorization(): void
    {
        $authRequest = $this->authServer->validateAuthorizationRequest($this->request);
        $authRequest->setUser(new Entity\User($this->getUser()));

//        // Display confirm auth page:
//        // this should be bypassed if the user has already given permission
//        // for this client.
//        // (but in this case, the access token will probably just be used)
//        $this->response = $this->responseFromTemplateUnthemed('@oidcServer/authorization.twig', []);

        // Could set redirect URI based on what's in the request?

        // For now, auto-accept
        $authRequest->setAuthorizationApproved(true);
        $this->response = $this->authServer->completeAuthorizationRequest($authRequest, $this->response);
    }

    /**
     * @see getAuthorization
     */
    public function postAuthorization(): void
    {
        $this->getAuthorization();
    }

    /**
     * Exchange a received auth code for an access token.
     *
     * @api   GET /oidc/token
     * @since 24.0
     *
     * @return void
     */
    public function getAccessToken(): void
    {
        $this->response = $this->authServer->respondToAccessTokenRequest(
            $this->request,
            $this->response,
        );
    }

    /**
     * @see getAccessToken
     */
    public function postAccessToken(): void
    {
        $this->getAccessToken();
    }

    /**
     * Generate a JSON Web Key Set (JWKS).
     *
     * Copied from `\Schoolbox\LTI\Platform\Controller::getJsonWebKeySet`:
     * however, this is in a more generic location.
     *
     * @api   GET /.well-known/jwks.json
     * @since 24.0
     */
    public function getJsonWebKeySet(): void
    {
        $jwksExporter   = new JwksExporter($this->keyChains);
        $this->response = $this->responseAsJson(
            $jwksExporter->export(RepositoryFactory::PLATFORM_KEYCHAIN_KEY_SET_NAME),
        );
    }
}
