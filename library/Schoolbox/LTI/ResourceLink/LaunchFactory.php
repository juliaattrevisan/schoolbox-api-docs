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
use OAT\Library\Lti1p3Core\Message\Payload\Claim\ContextClaim;
use OAT\Library\Lti1p3Core\Message\Payload\Claim\NrpsClaim;
use OAT\Library\Lti1p3Core\Message\Payload\Claim\ResourceLinkClaim;
use OAT\Library\Lti1p3Core\Registration\RegistrationRepositoryInterface;
use Schoolbox\IConfigLoader;
use Schoolbox\Instance;
use Schoolbox\Learning\Assessment\Lti\Model;
use Schoolbox\LTI\Util\CustomClaimFactory;
use Schoolbox\LTI\Util\PlatformInstanceClaimFactory;
use Schoolbox\LTI\Util\RoleClaimFactory;
use Schoolbox\LTI\Util\RoleScopeMentorClaimFactory;
use Schoolbox\LTI\Util\UserCodec;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Url\Factory as UrlFactory;
use Schoolbox\User;

class LaunchFactory
{
    private Access $access;

    private Router $router;

    private Instance $instance;

    private RegistrationRepositoryInterface $registrations;

    private UserCodec $userCodec;

    private IConfigLoader $config;

    public function __construct(
        Access $access,
        Router $router,
        RegistrationRepositoryInterface $registrations,
        Instance $instance,
        UserCodec $userCodec,
        IConfigLoader $config
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
        $assessmentContext = $assessment->getBaseContext()->getFolder();

        // Create an LTI resource link launch, send it to the iframe
        $registration = $this->registrations->find($assessment->getLtiTool()->getDeploymentId());

        // Roles: dependent on whether user is a participant or instructor
        // TODO determine how roles should be set if $actingUser != $currentUser
        $roleClaimFactory = new RoleClaimFactory($this->access);
        $roles            = array_merge(
            $roleClaimFactory->buildInstitutionRoles($currentUser),
            $roleClaimFactory->buildContextRolesForAssessment($currentUser, $assessment),
        );

        // Additional claims:
        $additionalClaims = [];

        // Resource link claim: talk about the assessment
        $resourceLinkClaim  = new ResourceLinkClaim(
            (string)$assessment->getId(),
            $assessment->getLtiAssessmentTitle(),
            $assessment->getLtiAssessmentText(),
        );
        $additionalClaims[] = $resourceLinkClaim;

        // Context claim: talk about the folder
        // I have seen no explanation for what context type actually is,
        // thus I will make something up.
        $contextClaim       = new ContextClaim(
            (string)$assessmentContext->getId(),
            [ContextClaim::TYPE_COURSE_SECTION],
            $assessmentContext->getCodeString(),
            $assessmentContext->getTitle(),
        );
        $additionalClaims[] = $contextClaim;

        // PlatformInstanceClaim: talk about the Schoolbox instance
        $platformInstanceClaim = (new PlatformInstanceClaimFactory($this->instance))->build();
        $additionalClaims[]    = $platformInstanceClaim;

        // Role-scope mentor claims:
        // for parents, this might be their kids
        // for kids, this won't be anyone
        // for teachers, this will be all participants in the assessment
        $roleScopeMentorClaim = (new RoleScopeMentorClaimFactory($this->access))
            ->buildForAssessment($currentUser, $assessment);
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
        $baseUrl            = (new UrlFactory($this->config))->buildFullyQualified()->setScheme('https');
        $additionalClaims[] = new AgsClaim(
            [ScoreServiceInterface::AUTHORIZATION_SCOPE_SCORE],
            null,
            (clone $baseUrl)
                ->setPath(
                    $this->router->generate(
                        'learningAssessment.getLineItem',
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
                        'folder.getMemberships',
                        ['id' => $assessment->getBaseContext()->getFolder()->getId()]
                    )
                )
                ->setQuery(['rlid' => $assessment->getId(),])
                ->toString(),
        );

        // Resource link request
        $builder = new PlatformOriginatingLaunchBuilder();
        return $builder->buildPlatformOriginatingLaunch(
            $registration,
            LtiMessageInterface::LTI_MESSAGE_TYPE_RESOURCE_LINK_REQUEST,
            $assessment->getLtiAssessmentUrl() ?? $assessment->getLtiTool()->getLaunchUrl(),
            $this->userCodec->encode($currentUser),
            null,
            array_unique($roles),
            $additionalClaims,
        );
    }
}
