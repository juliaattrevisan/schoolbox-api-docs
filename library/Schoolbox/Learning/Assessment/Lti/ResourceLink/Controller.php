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
use OAT\Library\Lti1p3Core\Registration\RegistrationRepositoryInterface;
use OAT\Library\Lti1p3Core\Role\RoleInterface;
use Schoolbox\Instance;
use Schoolbox\Learning\Assessment\Lti\Model as LtiAssessment;
use Schoolbox\LTI\Registration\Repository;
use Schoolbox\LTI\Util\PlatformInstanceClaimFactory;

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
     * Constructor
     *
     * @param Repository $repository
     * @param Instance   $instance
     */
    public function __construct(Repository $repository, Instance $instance)
    {
        $this->repository = $repository;
        $this->instance   = $instance;
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
        // Create an LTI resource link launch, send it to the iframe
        $registration = $this->repository->find($assessment->getLtiTool()->getDeploymentId());

        // Roles: dependent on whether user is a participant or instructor
        $roles = [
            $this->getUser()->getRole()->isStaff()
                ? RoleInterface::NAMESPACE_CONTEXT . '#Instructor'
                : RoleInterface::NAMESPACE_CONTEXT . '#Learner'
        ];

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
        $assessmentContext  = $assessment->getBaseContext()->getFolder();
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
        // for teachers, this will probably be all participants in the assessment

        // Launch presentation:
        // should come from assessment, but doesn't yet

        // LIS claim: I don't think we support this yet

        // Custom claims, with whatever was passed from the product
        foreach (($assessment->getCustomFields() ?? []) as $key => $value) {
            // TODO apply substitutions to value
            $additionalClaims[$key] = $value;
        }


        // Resource link request
        $builder = new PlatformOriginatingLaunchBuilder();
        $message = $builder->buildPlatformOriginatingLaunch(
            $registration,
            LtiMessageInterface::LTI_MESSAGE_TYPE_RESOURCE_LINK_REQUEST,
            $assessment->getLtiAssessmentUrl() ?? $assessment->getLtiTool()->getLaunchUrl(),
            $this->getUser()->getUsername(),
            null,
            $roles,
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
