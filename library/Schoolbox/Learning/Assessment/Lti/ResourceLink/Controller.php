<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Lti\ResourceLink;

use Alaress\Web\Controller\Base;
use OAT\Library\Lti1p3Core\Message\Launch\Builder\PlatformOriginatingLaunchBuilder;
use OAT\Library\Lti1p3Core\Message\LtiMessageInterface;
use OAT\Library\Lti1p3Core\Message\Payload\Claim\ContextClaim;
use OAT\Library\Lti1p3Core\Message\Payload\Claim\ResourceLinkClaim;
use OAT\Library\Lti1p3Core\Message\Payload\LtiMessagePayloadInterface;
use OAT\Library\Lti1p3Core\Registration\RegistrationRepositoryInterface;
use OAT\Library\Lti1p3Core\Role\RoleInterface;
use Schoolbox\Instance;
use Schoolbox\Learning\Assessment\Lti\Model as LtiAssessment;
use Schoolbox\LTI\Registration\Repository;
use Schoolbox\LTI\Util\PlatformInstanceClaimFactory;
use Schoolbox\LTI\Util\RoleClaimFactory;
use Schoolbox\LTI\Util\RoleScopeMentorClaimFactory;
use Schoolbox\Permission\Access\Access;

class Controller extends Base
{
    /**
     * Repository of tool+platform configurations.
     */
    private RegistrationRepositoryInterface $repository;

    /**
     * Instance data repository.
     */
    private Instance $instance;

    /**
     * Access permission checker.
     */
    private Access $access;

    /**
     * Constructor
     *
     * @param Repository $repository
     * @param Instance   $instance
     * @param Access     $access
     */
    public function __construct(Repository $repository, Instance $instance, Access $access)
    {
        $this->repository = $repository;
        $this->instance   = $instance;
        $this->access     = $access;
    }

    /**
     * Launch a resource link for the given assessment, as the current user.
     *
     * @api GET /learning/launch/{assessmentId}
     * @since 21.1.0
     *
     * @param LtiAssessment $assessment
     */
    public function getLaunchAssessment(LtiAssessment $assessment): void
    {
        $assessmentContext = $assessment->getBaseContext()->getFolder();

        // Create an LTI resource link launch, send it to the iframe
        $registration = $this->repository->find($assessment->getLtiTool()->getDeploymentId());

        // Roles: dependent on whether user is a participant or instructor
        $roleClaimFactory = new RoleClaimFactory($this->access);
        $roles            = array_merge(
            $roleClaimFactory->buildInstitutionRoles($this->getUser()),
            $roleClaimFactory->buildContextRolesForAssessment($this->getUser(), $assessment),
        );


        // Additional claims:
        $additionalClaims = [];

        // Resource link claim: talk about the assessment
        $additionalClaims[] = new ResourceLinkClaim(
            (string)$assessment->getId(),
            $assessment->getLtiAssessmentTitle(),
            $assessment->getLtiAssessmentText(),
        );

        // Context claim: talk about the folder
        // I have seen no explanation for what context type actually is,
        // thus I will make something up.
        $additionalClaims[] = new ContextClaim(
            (string)$assessmentContext->getId(),
            [ContextClaim::TYPE_COURSE_SECTION],
            $assessmentContext->getCodeString(),
            $assessmentContext->getTitle(),
        );

        // PlatformInstanceClaim: talk about the Schoolbox instance
        $additionalClaims[] = (new PlatformInstanceClaimFactory($this->instance))->build();

        // Role-scope mentor claims:
        // for parents, this might be their kids
        // for kids, this won't be anyone
        // for teachers, this will be all participants in the assessment
        $roleScopeMentorClaim = (new RoleScopeMentorClaimFactory($this->access))
            ->buildForAssessment($this->getUser(), $assessment);
        if ($roleScopeMentorClaim) {
            $additionalClaims[] = $roleScopeMentorClaim;
        }

        // Launch presentation:
        // should come from assessment, but doesn't yet

        // LIS claim: I don't think we support this yet

        // Custom claims, with whatever was passed from the product
        foreach (($assessment->getCustomFields() ?? []) as $key => $value) {
            // TODO apply substitutions to value
            $additionalClaims[$key] = $value;
        }

        // AGS claims:
        // if a line item is provided, send that

        // Resource link request
        $builder = new PlatformOriginatingLaunchBuilder();
        $message = $builder->buildPlatformOriginatingLaunch(
            $registration,
            LtiMessageInterface::LTI_MESSAGE_TYPE_RESOURCE_LINK_REQUEST,
            $assessment->getLtiAssessmentUrl() ?? $assessment->getLtiTool()->getLaunchUrl(),
            $this->getUser()->getUsername(),
            null,
            array_unique($roles),
            $additionalClaims,
        );

        // And send message to template, where it's POSTed
        $this->response->content->set(
            $this->buildUnthemedHtmlOutput(
                '@learningLtiResourceLink/messageForm.twig',
                ['message' => $message]
            )
        );
    }
}
