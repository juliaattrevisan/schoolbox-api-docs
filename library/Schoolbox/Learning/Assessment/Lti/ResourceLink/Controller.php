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
use OAT\Library\Lti1p3Core\Message\Payload\Claim\PlatformInstanceClaim;
use OAT\Library\Lti1p3Core\Message\Payload\Claim\ResourceLinkClaim;
use OAT\Library\Lti1p3Core\Registration\RegistrationRepositoryInterface;
use Schoolbox\Instance;
use Schoolbox\Learning\Assessment\Lti\Model as LtiAssessment;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Url\Factory;

class Controller extends Base
{
    /**
     * Repository of tool+platform configurations.
     */
    private RegistrationRepositoryInterface $repository;

    /**
     * Instance data repository
     */
    private Instance $instance;

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


        // Return URL
        $baseUrl   = (new Factory($this->getConfigLoader()))->buildFullyQualified()->setScheme('https');
        $returnUrl = (clone $baseUrl)
            ->setPath(
                Redirect::decode($this->request->query->get(Redirect::RETURN_URL))
            )
            ->toString();


        // Roles: dependent on whether user is a participant or instructor
        $roles = [];


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
        // TODO this should be factoryized
        $additionalClaims[] = new PlatformInstanceClaim(
            $this->instance->getId(),
            null,
            null,
            null,
            (clone $baseUrl)->toString(),
            Instance::APPLICATION_NAME,
            $this->instance->getVersion(),
        );

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
    }
}
