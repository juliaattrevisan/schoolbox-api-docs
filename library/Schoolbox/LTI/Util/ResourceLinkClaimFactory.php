<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Util;

use OAT\Library\Lti1p3Core\Message\Payload\Claim\ResourceLinkClaim;
use Schoolbox\Learning\Assessment\Lti\Model as LtiAssessment;

/**
 * Factory for building resource link claims.
 */
class ResourceLinkClaimFactory
{
    /**
     * Builds a resource link claim from an assessment.
     *
     * @param LtiAssessment $assessment
     * @return ResourceLinkClaim
     */
    public function buildForAssessment(LtiAssessment $assessment): ResourceLinkClaim
    {
        // TODO Resource link's identifier should be a FQDN, with a codec
        return new ResourceLinkClaim(
            (string)$assessment->getId(),
            $assessment->getLtiAssessmentTitle(),
            $assessment->getLtiAssessmentText(),
        );
    }
}
