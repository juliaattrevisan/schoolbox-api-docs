<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Platform\Context;

use Alaress\Web\Controller\Base as BaseController;
use League\OAuth2\Server\ResourceServer;
use OAT\Library\Lti1p3Core\Registration\RegistrationRepositoryInterface;
use OAT\Library\Lti1p3Core\Resource\LtiResourceLink\LtiResourceLinkInterface;
use OAT\Library\Lti1p3Core\Security\OAuth2\Repository\AccessTokenRepository;
use OAT\Library\Lti1p3Core\Security\OAuth2\Validator\RequestAccessTokenValidator;
use OAT\Library\Lti1p3Core\Service\Server\LtiServiceServer;
use OAT\Library\Lti1p3DeepLinking\Message\Launch\Builder\DeepLinkingLaunchRequestBuilder;
use OAT\Library\Lti1p3DeepLinking\Settings\DeepLinkingSettings;
use OAT\Library\Lti1p3Nrps\Service\Server\Handler\MembershipServiceServerRequestHandler;
use Psr\Cache\CacheItemPoolInterface;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Instance;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\LTI\Context\Folder as ContextFolder;
use Schoolbox\LTI\Membership\Builder;
use Schoolbox\LTI\Platform\Instance\ClaimFactory as PlatformInstanceClaimFactory;
use Schoolbox\LTI\Provider\Model as ToolModel;
use Schoolbox\LTI\ResourceLink\LaunchFactory;
use Schoolbox\LTI\Role\ClaimFactory as RoleClaimFactory;
use Schoolbox\LTI\User\Codec as UserCodec;
use Schoolbox\LTI\Util\ServerRequestFactory;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Url\Factory;

/**
 * LTI platform controller for context requests.
 *
 * Currently, this only contains an endpoint for fetching membership of a
 * context.
 */
class Controller extends BaseController
{
    /**
     * Repository of tool+platform registrations.
     */
    private RegistrationRepositoryInterface $registrations;

    /**
     * User codec, used to encode a user into a user ID when attaching mentors.
     */
    private UserCodec $userCodec;

    /**
     * Assessment data source, used to load an assessment's participation.
     */
    private AssessmentDataSource $assessmentDataSource;

    /**
     * User access mapper, used for retrieving the users within a folder.
     */
    private UserAccessMapper $userAccessMapper;

    /**
     * Access class, used for retrieving the user's level of access to a folder.
     */
    private Access $access;

    /**
     * Instance data repository, used to build resource link launch data.
     */
    private Instance $instance;

    /**
     * Cache for access tokens.
     */
    private CacheItemPoolInterface $accessTokenCache;

    /**
     * Constructor
     *
     * @param RegistrationRepositoryInterface $registrations
     * @param UserCodec                       $userCodec
     * @param AssessmentDataSource            $assessmentDataSource
     * @param Instance                        $instance
     * @param UserAccessMapper                $userAccessMapper
     * @param Access                          $access
     * @param CacheItemPoolInterface          $accessTokenCache
     */
    public function __construct(
        RegistrationRepositoryInterface $registrations,
        UserCodec $userCodec,
        AssessmentDataSource $assessmentDataSource,
        Instance $instance,
        UserAccessMapper $userAccessMapper,
        Access $access,
        CacheItemPoolInterface $accessTokenCache
    ) {
        $this->registrations        = $registrations;
        $this->userCodec            = $userCodec;
        $this->assessmentDataSource = $assessmentDataSource;
        $this->instance             = $instance;
        $this->userAccessMapper     = $userAccessMapper;
        $this->access               = $access;
        $this->accessTokenCache     = $accessTokenCache;
    }

    /**
     * Generate a deep linking request for the given folder and tool.
     *
     * @api   GET /lti-plaform/context/folder/{folderId}/deep-link/{toolId}
     * @since 21.1.0
     *
     * @param ToolModel $tool
     * @param Folder    $folder
     */
    public function getGenerateDeepLinkingRequestForFolder(Folder $folder, ToolModel $tool)
    {
        // Find the tool in the list of registrations
        $repository = $this->registrations->find($tool->getDeploymentId());

        // Return URL:
        // this is somewhere else
        $baseUrl   = (new Factory($this->getConfigLoader()))->buildFullyQualified()->setScheme('https');
        $returnUrl = (clone $baseUrl)
            ->setPath(
                $this->router->generate('ltiPlatform.getReceiveDeepLinkingResponse', ['toolId' => $tool->getId()])
            )
            ->toString();

        // Standard deep linking settings
        $deepLinkingSettings = new DeepLinkingSettings(
            $returnUrl,
            [LtiResourceLinkInterface::TYPE,],
            ['window', 'iframe'],
            null,
            false,
            false,
        );

        // Figure out who's trying to log in: use that to determine their role
        $roleClaimFactory = new RoleClaimFactory($this->access);
        $roles            = array_merge(
            $roleClaimFactory->buildInstitutionRoles($this->getUser()),
            $roleClaimFactory->buildContextRolesForFolder($this->getUser(), $folder),
        );

        // Claim about the context
        $contextClaimFactory = new ContextFolder\ClaimFactory(new ContextFolder\Codec($baseUrl));
        $contextClaim        = $contextClaimFactory->buildForFolder($folder);

        // Claim about the platform instance
        $platformInstanceClaim = (new PlatformInstanceClaimFactory($this->instance))->build();

        $builder = new DeepLinkingLaunchRequestBuilder();
        $message = $builder->buildDeepLinkingLaunchRequest(
            $deepLinkingSettings,
            $repository,
            $this->userCodec->encode($this->getUser()),
            null,
            null,
            $roles,
            [$platformInstanceClaim, $contextClaim],
        );

        // Build a DL request to this tool
        $this->response->content->set(
            $this->buildUnthemedHtmlOutput(
                '@ltiPlatform/messageForm.twig',
                ['message' => $message],
            )
        );
    }

    /**
     * LTI 1.3 NPRS endpoint: retrieves this folder's members.
     *
     * A `rlid` (resource link ID) query string parameter may be supplied:
     * if supplied, that corresponds to an LTI assessment's ID, and the unique
     * membership for that assessment will be supplied instead.
     *
     * @api   GET /lti-plaform/context/folder/{folderId}/memberships
     * @since 21.1.0
     *
     * @param Folder $folder
     */
    public function getMembershipsForFolder(Folder $folder): void
    {
        // Build and validate membership request
        // https://oat-sa.github.io/doc-lti1p3/libraries/lib-lti1p3-nrps/doc/platform/

        // Access token sanity checker:
        // validates the request token was created by a tool
        $validator = new RequestAccessTokenValidator($this->registrations);

        // Access token validity checker:
        // validate the access token hasn't expired
        $resourceServerValidator = new ResourceServer(
            new AccessTokenRepository($this->accessTokenCache),
            $this->getConfigLoader()->get(ConfigMapper::NAME_JWT_PUBLIC_KEY),
        );
        $request = $resourceServerValidator->validateAuthenticatedRequest(
            (new ServerRequestFactory())->build($this->request)
        );

        // Handle the request
        $builder = new Builder(
            $folder,
            $this->userAccessMapper,
            $this->assessmentDataSource,
            $this->getConfigLoader(),
            $this->userCodec,
            new LaunchFactory(
                $this->registrations,
                $this->access,
                $this->router,
                $this->instance,
                $this->getConfigLoader(),
                $this->userCodec,
            ),
        );
        $handler = new MembershipServiceServerRequestHandler($builder);
        $server  = new LtiServiceServer($validator, $handler);
        $this->populateResponseFromPsr7Response($server->handle($request));
    }
}
