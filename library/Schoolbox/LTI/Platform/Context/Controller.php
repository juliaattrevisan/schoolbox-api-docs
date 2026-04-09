<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Platform\Context;

use Alaress\Web\Controller\Base as BaseController;
use Component_ControllerAbstract;
use JsonException;
use League\OAuth2\Server\ResourceServer;
use OAT\Library\Lti1p3Ags\Service\LineItem\LineItemServiceInterface;
use OAT\Library\Lti1p3Core\Message\Launch\Builder\LtiResourceLinkLaunchRequestBuilder;
use OAT\Library\Lti1p3Core\Message\Payload\Builder\MessagePayloadBuilder;
use OAT\Library\Lti1p3Core\Message\Payload\Claim\AgsClaim;
use OAT\Library\Lti1p3Core\Message\Payload\Claim\LaunchPresentationClaim;
use OAT\Library\Lti1p3Core\Message\Payload\Claim\MessagePayloadClaimInterface;
use OAT\Library\Lti1p3Core\Message\Payload\Claim\NrpsClaim;
use OAT\Library\Lti1p3Core\Registration\RegistrationRepositoryInterface;
use OAT\Library\Lti1p3Core\Resource\LtiResourceLink\LtiResourceLink;
use OAT\Library\Lti1p3Core\Resource\LtiResourceLink\LtiResourceLinkInterface;
use OAT\Library\Lti1p3Core\Security\Jwt\Builder\Builder as JwtBuilder;
use OAT\Library\Lti1p3Core\Security\OAuth2\Repository\AccessTokenRepository;
use OAT\Library\Lti1p3Core\Security\OAuth2\Validator\RequestAccessTokenValidator;
use OAT\Library\Lti1p3DeepLinking\Message\Launch\Builder\DeepLinkingLaunchRequestBuilder;
use OAT\Library\Lti1p3DeepLinking\Settings\DeepLinkingSettings;
use OAT\Library\Lti1p3Nrps\Service\Server\Handler\MembershipServiceServerRequestHandler;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Instance;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\LTI\Component\Homepage\Controller as LtiComponent;
use Schoolbox\LTI\Context\Folder as ContextFolder;
use Schoolbox\LTI\Custom\ClaimFactory as CustomClaimFactory;
use Schoolbox\LTI\Membership\Builder;
use Schoolbox\LTI\Platform\Instance\ClaimFactory as PlatformInstanceClaimFactory;
use Schoolbox\LTI\Platform\Server\ServiceServer;
use Schoolbox\LTI\Provider\Model as ToolModel;
use Schoolbox\LTI\ResourceLink\LaunchFactory;
use Schoolbox\LTI\Role\ClaimFactory as RoleClaimFactory;
use Schoolbox\LTI\User\Codec as UserCodec;
use Schoolbox\LTI\Util\LogHelper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Url\Factory;
use Schoolbox\User;

/**
 * LTI platform controller for context requests.
 *
 * Currently, this only contains an endpoint for fetching membership of a
 * context.
 */
class Controller extends BaseController
{
    /**
     * Constructor
     *
     * @param RegistrationRepositoryInterface $registrations        Repository of tool+platform registrations.
     * @param UserCodec                       $userCodec            User codec, used to encode a user into a user ID
     *                                                              when attaching mentors.
     * @param AssessmentDataSource            $assessmentDataSource Assessment data source, used to load an assessment's
     *                                                              participation.
     * @param Instance                        $instance             Instance data repository, used to build resource
     *                                                              link launch data.
     * @param UserAccessMapper                $userAccessMapper     User access mapper, used for retrieving the users
     *                                                              within a folder.
     * @param Access                          $access               Access class, used for retrieving the user's level
     *                                                              of access to a folder.
     * @param CacheItemPoolInterface          $accessTokenCache     Cache for access tokens.
     * @param LoggerInterface                 $logger               Logger for what this controller does.
     */
    public function __construct(
        private RegistrationRepositoryInterface $registrations,
        private UserCodec $userCodec,
        private AssessmentDataSource $assessmentDataSource,
        private Instance $instance,
        private UserAccessMapper $userAccessMapper,
        private Access $access,
        private CacheItemPoolInterface $accessTokenCache,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Generate a resource link launch request for the given folder and
     * component.
     *
     * (The component will have a tool within it.)
     *
     * @api   GET /lti-platform/context/folder/{folderId}/launch/component/{componentId}
     * @since 21.1.0
     *
     * @param Folder                       $folder
     * @param Component_ControllerAbstract $component
     * @param ToolModel                    $tool
     */
    public function getLaunchResourceLinkRequestForFolderAndComponent(
        Folder $folder,
        Component_ControllerAbstract $component,
        ToolModel $tool
    ): void {
        LogHelper::logControllerAction($this->logger, __METHOD__, $this->request);
        // Build a resource link
        $resourceLink = new LtiResourceLink(
            'component-' . $component->getInstance()->getId(),
            [
                'url'   => $component->getConfig(LtiComponent::COMPONENT_CONFIG_LTI_EMBED_URL),
                'title' => $component->getConfig(LtiComponent::COMPONENT_CONFIG_TITLE),
            ]
        );

        // Handle custom claims
        $componentCustomClaims = [];
        try {
            $componentCustomClaims = json_decode(
                $component->getConfig(LtiComponent::COMPONENT_CONFIG_LTI_CUSTOM_PARAMETERS),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $e) {
            // noop
        }
        if (!is_array($componentCustomClaims)) {
            $componentCustomClaims = [];
        }

        /* If the custom claims are empty, set it to null so not to send to the LTI tool
         * this claim is optional (well should be) so we can safely remove it
         */
        if (empty($componentCustomClaims)) {
            $componentCustomClaims = null;
        }

        // Build the launch request for the resource link
        $repository       = $this->registrations->find($tool->getDeploymentId());
        $messageBuilder   = new LtiResourceLinkLaunchRequestBuilder();
        $additionalClaims = $this->buildAdditionalClaims(
            $folder,
            $componentCustomClaims,
            $this->getUser(),
            null,
        );
        $additionalClaims[] = $this->buildLaunchPresentationClaim($tool, $component);
        $message            = $messageBuilder->buildLtiResourceLinkLaunchRequest(
            $resourceLink,
            $repository,
            $this->userCodec->encode($this->getUser()),
            null,
            $this->buildRoles($folder),
            $additionalClaims,
        );

        $this->setAllowBackToTheFuture(false);

        // Send the request
        $this->response = $this->responseFromTemplateUnthemed(
            '@ltiPlatform/messageForm.twig',
            ['message' => $message],
        );
    }

    /**
     * Generate a deep linking request for the given folder and tool.
     *
     * @api   GET /lti-platform/context/folder/{folderId}/deep-linking-request/{toolId}
     * @since 21.1.0
     *
     * @param ToolModel $tool
     * @param Folder    $folder
     */
    public function getGenerateDeepLinkingRequestForFolder(Folder $folder, ToolModel $tool): void
    {
        LogHelper::logControllerAction($this->logger, __METHOD__, $this->request);
        // Find the tool in the list of registrations
        $repository = $this->registrations->find($tool->getDeploymentId());

        // Return URL:
        // this is somewhere else
        $baseUrl   = new Factory($this->getConfigLoader())->buildFullyQualified()->withScheme('https');
        $returnUrl = (clone $baseUrl)
            ->withPath(
                $this->routeGenerator->generate('ltiPlatform.getReceiveDeepLinkingResponse', ['toolId' => $tool->getId()])
            )
            // If a context was passed in the query string:
            // add it to the return URL
            ->appendQueryParameters(['context' => $this->requestQuery->get('context')]);

        // Standard deep linking settings
        $deepLinkingSettings = new DeepLinkingSettings(
            (string)$returnUrl,
            [LtiResourceLinkInterface::TYPE,],
            [$tool->getTarget(),],
            null,
            false,
            false,
        );

        $builder = new DeepLinkingLaunchRequestBuilder(
            new MessagePayloadBuilder(null, new JwtBuilder(new ConfigurationFactory()))
        );

        $message = $builder->buildDeepLinkingLaunchRequest(
            $deepLinkingSettings,
            $repository,
            $this->userCodec->encode($this->getUser()),
            null,
            null,
            $this->buildRoles($folder),
            $this->buildAdditionalClaims($folder, null, null, $this->requestQuery->get('lineItemGuid')),
        );

        $this->setAllowBackToTheFuture(false);

        // Build a DL request to this tool
        $this->response = $this->responseFromTemplateUnthemed(
            '@ltiPlatform/messageForm.twig',
            ['message' => $message],
        );
    }

    /**
     * LTI 1.3 NPRS endpoint: retrieves this folder's members.
     *
     * A `rlid` (resource link ID) query string parameter may be supplied:
     * if supplied, that corresponds to an LTI assessment's ID, and the unique
     * membership for that assessment will be supplied instead.
     *
     * @api   GET /lti-platform/context/folder/{folderId}/memberships
     * @since 21.1.0
     *
     * @param Folder $folder
     */
    public function getMembershipsForFolder(Folder $folder): void
    {
        LogHelper::logControllerAction($this->logger, __METHOD__, $this->request);
        // Build and validate membership request
        // https://oat-sa.github.io/doc-lti1p3/libraries/lib-lti1p3-nrps/doc/platform/

        // Access token sanity checker:
        // validates the request token was created by a tool
        $validator = new RequestAccessTokenValidator($this->registrations, $this->logger);

        // Access token validity checker:
        // validate the access token hasn't expired
        $resourceServerValidator = new ResourceServer(
            new AccessTokenRepository($this->accessTokenCache, $this->logger),
            $this->getConfigLoader()->get(ConfigMapper::NAME_JWT_PUBLIC_KEY),
        );
        $request = $resourceServerValidator->validateAuthenticatedRequest($this->request);

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
                $this->routeGenerator,
                $this->instance,
                $this->getConfigLoader(),
                $this->userCodec,
            ),
        );
        $handler            = new MembershipServiceServerRequestHandler($builder);
        $server             = new ServiceServer($validator, $handler, logger: $this->logger);
        $this->response = $server->handle($request);
    }

    /**
     * Build the list of roles for an LTI message for this folder.
     *
     * @param Folder $folder
     * @return array
     * @throws MapperException if somehow finding the context roles for the
     *                         folder goes wrong
     */
    private function buildRoles(Folder $folder): array
    {
        $roleClaimFactory = new RoleClaimFactory($this->access);
        return array_merge(
            $roleClaimFactory->buildInstitutionRoles($this->getUser()),
            $roleClaimFactory->buildContextRolesForFolder($this->getUser(), $folder),
        );
    }

    /**
     * Builds the list of additional claims for LTI messages from this folder:
     * * a context claim for the folder itself
     * * a platform instance claim for the Schoolbox instance
     *
     * @param Folder      $folder
     * @param array|null  $customClaims If provided, use these custom claims
     * @param User|null   $user         If provided, add user to custom claims
     * @param string|null $lineItemGuid If provided: a GUID which identifies the place to which the LTI tool may post
     *                                  line items to.
     * @return array<MessagePayloadClaimInterface>
     */
    private function buildAdditionalClaims(
        Folder $folder,
        ?array $customClaims,
        ?User $user,
        ?string $lineItemGuid,
    ): array {
        $baseUrl = (new Factory($this->getConfigLoader()))->buildFullyQualified();

        // Claim about the context
        $contextClaimFactory = new ContextFolder\ClaimFactory(new ContextFolder\Codec($baseUrl));
        $contextClaim        = $contextClaimFactory->buildForFolder($folder);

        // Claim about the platform instance
        $platformInstanceClaim = new PlatformInstanceClaimFactory($this->instance, $baseUrl)->build();

        // Claim about folder memberships
        $nrpsClaim = new NrpsClaim(
            (string)(clone $baseUrl)
                ->withPath(
                    $this->routeGenerator->generate(
                        'ltiPlatformContext.getMembershipsForFolder',
                        ['folderId' => $folder->getId()]
                    )
                ),
            ['2.0'],
        );

        $claims = [$contextClaim, $platformInstanceClaim, $nrpsClaim];

        // Custom claims for the component
        $customClaimFactory = new CustomClaimFactory();
        $customClaimFactory = $customClaimFactory
            ->withContext($contextClaim)
            ->withPlatformInstance($platformInstanceClaim);
        if (isset($user)) {
            $customClaimFactory = $customClaimFactory
                ->withUser($user)
                ->withActualUser($user);
        }
        $customClaim = $customClaimFactory->build($customClaims);
        if (isset($customClaim)) {
            $claims[] = $customClaim;
        }

        // Line items claim, if GUID is provided
        if (isset($lineItemGuid)) {
            $agsClaim = new AgsClaim(
                [LineItemServiceInterface::AUTHORIZATION_SCOPE_LINE_ITEM],
                (string)(clone $baseUrl)
                    ->withPath(
                        $this->routeGenerator->generate(
                            'ltiPlatformLineItemGuid.postLineItemForGuid',
                            ['guid' => $lineItemGuid],
                        ),
                    ),
            );
            $claims[] = $agsClaim;
        }

        return $claims;
    }

    /**
     * Build a launch presentation claim for the given tool within the given
     * component.
     *
     * @param ToolModel                    $tool
     * @param Component_ControllerAbstract $component
     * @return LaunchPresentationClaim
     */
    private function buildLaunchPresentationClaim(
        ToolModel $tool,
        Component_ControllerAbstract $component
    ): LaunchPresentationClaim {
        $baseUrl = new Factory($this->getConfigLoader())->buildFullyQualified();
        return new LaunchPresentationClaim(
            $tool->getTarget(),
            $component->getConfig(LtiComponent::COMPONENT_CONFIG_HEIGHT),
            null,
            (string)$baseUrl
                ->withPath(sprintf('/homepage/%d', $component->getInstance()->getIdentifier()))
                ->withFragment(sprintf('component%d', $component->getInstance()->getId())),
        );
    }
}
