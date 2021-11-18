<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\ResourceLink;

use Aura\Router\Router;
use OAT\Library\Lti1p3Ags\Service\Score\ScoreServiceInterface;
use OAT\Library\Lti1p3Core\Message\Launch\Builder\PlatformOriginatingLaunchBuilder;
use OAT\Library\Lti1p3Core\Message\LtiMessageInterface;
use OAT\Library\Lti1p3Core\Message\Payload\Claim\AgsClaim;
use OAT\Library\Lti1p3Core\Message\Payload\Claim\MessagePayloadClaimInterface;
use OAT\Library\Lti1p3Core\Message\Payload\Claim\NrpsClaim;
use OAT\Library\Lti1p3Core\Registration\RegistrationRepositoryInterface;
use Schoolbox\IConfigLoader;
use Schoolbox\Instance;
use Schoolbox\Learning\Assessment\Lti\Model;
use Schoolbox\LTI\Context\Folder\ClaimFactory as FolderClaimFactory;
use Schoolbox\LTI\Context\Folder\Codec as FolderCodec;
use Schoolbox\LTI\Custom\ClaimFactory as CustomClaimFactory;
use Schoolbox\LTI\Platform\Instance\ClaimFactory as PlatformInstanceClaimFactory;
use Schoolbox\LTI\ResourceLink\Assessment\ClaimFactory as AssessmentClaimFactory;
use Schoolbox\LTI\ResourceLink\Assessment\Codec as AssessmentCodec;
use Schoolbox\LTI\Role\ClaimFactory as RoleClaimFactory;
use Schoolbox\LTI\Role\ScopeMentor\ClaimFactory as RoleScopeMentorClaimFactory;
use Schoolbox\LTI\User\Codec as UserCodec;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Url\Factory as UrlFactory;
use Schoolbox\User;

/**
 * Factory for building resource link launches.
 */
class LaunchFactory
{
    /**
     * Registration repository, used to find the registration to launch a
     * resource link within the context of.
     */
    private RegistrationRepositoryInterface $registrations;

    /**
     * User access class: used to determine the roles in the resource link
     * launch.
     */
    private Access $access;

    /**
     * Router: used to build links to other content.
     */
    private Router $router;

    /**
     * Schoolbox instance data: used to build the platform instance claim.
     */
    private Instance $instance;

    /**
     * Config: currently used to build a fully-qualified domain name for use
     * within generated links.
     */
    private IConfigLoader $config;

    /**
     * User codec: used to encode/decode users for inclusion within the launch.
     */
    private UserCodec $userCodec;

    /**
     * Constructor
     *
     * @param RegistrationRepositoryInterface $registrations
     * @param Access                          $access
     * @param Router                          $router
     * @param Instance                        $instance
     * @param IConfigLoader                   $config
     * @param UserCodec                       $userCodec
     */
    public function __construct(
        RegistrationRepositoryInterface $registrations,
        Access $access,
        Router $router,
        Instance $instance,
        IConfigLoader $config,
        UserCodec $userCodec
    ) {
        $this->access        = $access;
        $this->router        = $router;
        $this->registrations = $registrations;
        $this->instance      = $instance;
        $this->userCodec     = $userCodec;
        $this->config        = $config;
    }

    /**
     * Build a resource link launch message, which will then be used to launch
     * the LTI tool's assessment.
     *
     * @param Model $assessment
     * @param User  $currentUser The current user
     * @param User  $actingUser  The user who the current user is acting as:
     *                           if not themselves, it may be a student.
     * @return LtiMessageInterface
     */
    public function buildForAssessment(
        Model $assessment,
        User $currentUser,
        User $actingUser
    ): LtiMessageInterface {
        // Roles: dependent on whether user is a participant or instructor
        // TODO determine how roles should be set if $actingUser != $currentUser
        $roleClaimFactory = new RoleClaimFactory($this->access);
        $roles            = array_merge(
            $roleClaimFactory->buildInstitutionRoles($actingUser),
            $roleClaimFactory->buildContextRolesForAssessment($actingUser, $assessment),
        );

        // Build additional claims
        $additionalClaims = $this->buildAdditionalClaims($assessment, $currentUser, $actingUser);

        // Create an LTI resource link launch, send it to the iframe
        $registration = $this->registrations->find($assessment->getLtiTool()->getDeploymentId());

        // Resource link request
        $builder = new PlatformOriginatingLaunchBuilder();
        return $builder->buildPlatformOriginatingLaunch(
            $registration,
            LtiMessageInterface::LTI_MESSAGE_TYPE_RESOURCE_LINK_REQUEST,
            $assessment->getLtiAssessmentUrl() ?? $assessment->getLtiTool()->getLaunchUrl(),
            $this->userCodec->encode($actingUser),
            null,
            array_unique($roles),
            $additionalClaims,
        );
    }

    /**
     * Build a list of additional claims for this resource link launch.
     *
     * @param Model $assessment
     * @param User  $currentUser
     * @param User  $actingUser
     * @return array<MessagePayloadClaimInterface>
     */
    public function buildAdditionalClaims(
        Model $assessment,
        User $currentUser,
        User $actingUser
    ): array {
        $baseUrl = (new UrlFactory($this->config))->buildFullyQualified()->setScheme('https');

        $additionalClaims = [];

        // Resource link claim: talk about the assessment
        $resourceLinkCodec  = new AssessmentCodec($baseUrl);
        $resourceLinkClaim  = (new AssessmentClaimFactory($resourceLinkCodec))
            ->buildForAssessment($assessment);
        $additionalClaims[] = $resourceLinkClaim;

        // Context claim: talk about the folder
        $contextClaim       = (new FolderClaimFactory(new FolderCodec($baseUrl)))
            ->buildForFolder($assessment->getBaseContext()->getFolder());
        $additionalClaims[] = $contextClaim;

        // PlatformInstanceClaim: talk about the Schoolbox instance
        $platformInstanceClaim = (new PlatformInstanceClaimFactory($this->instance))->build();
        $additionalClaims[]    = $platformInstanceClaim;

        // Role-scope mentor claims:
        // for parents, this might be their kids
        // for kids, this won't be anyone
        // for teachers, this will be all participants in the assessment
        $roleScopeMentorClaim = (new RoleScopeMentorClaimFactory($this->access, $this->userCodec))
            ->buildForAssessment($actingUser, $assessment);
        if ($roleScopeMentorClaim) {
            $additionalClaims[] = $roleScopeMentorClaim;
        }

        // Launch presentation:
        // should come from assessment, but doesn't yet

        // LIS claim: I don't think we support this yet

        // Custom claims, with whatever was passed from the product
        $customClaim = (new CustomClaimFactory())
            ->withUser($actingUser)
            ->withActualUser($currentUser)
            ->withResourceLink($resourceLinkClaim)
            ->withContext($contextClaim)
            ->withPlatformInstance($platformInstanceClaim)
            ->buildForAssessment($assessment);
        if ($customClaim) {
            $additionalClaims[] = $customClaim;
        }

        // AGS claims:
        // if a line item is provided, send that
        // (Currently, score is the only service Schoolbox provides)
        $additionalClaims[] = new AgsClaim(
            [ScoreServiceInterface::AUTHORIZATION_SCOPE_SCORE],
            null,
            (clone $baseUrl)
                ->setPath(
                    $this->router->generate(
                        'ltiPlatformLineItem.getLineItemForAssessment',
                        ['assessmentId' => $assessment->getId()]
                    )
                )
                ->toString(),
        );

        // NPRS claim:
        // provide the folder as the context, with a resource ID parameter
        $additionalClaims[] = new NrpsClaim(
            (clone $baseUrl)
                ->setPath(
                    $this->router->generate(
                        'ltiPlatformContext.getMembershipsForFolder',
                        ['folderId' => $assessment->getBaseContext()->getFolder()->getId()]
                    )
                )
                ->setQuery(['rlid' => $resourceLinkCodec->encode($assessment->getId()),])
                ->toString(),
        );

        return $additionalClaims;
    }
}
