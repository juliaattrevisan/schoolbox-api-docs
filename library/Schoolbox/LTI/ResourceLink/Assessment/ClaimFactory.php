<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\ResourceLink\Assessment;

use Alaress\Codec\ICodec;
use OAT\Library\Lti1p3Core\Message\Payload\Claim\ResourceLinkClaim;
use Schoolbox\Learning\Assessment\Lti\Model as LtiAssessment;

/**
 * Factory for building resource link claims from an assessment.
 */
class ClaimFactory
{
    /**
     * Codec, for encoding resource link ID into a claim identifier.
     */
    private ICodec $codec;

    /**
     * Constructor
     *
     * @param ICodec $codec
     */
    public function __construct(ICodec $codec)
    {
        $this->codec = $codec;
    }

    /**
     * Builds a resource link claim from an assessment.
     *
     * @param LtiAssessment $assessment
     * @return ResourceLinkClaim
     */
    public function buildForAssessment(LtiAssessment $assessment): ResourceLinkClaim
    {
        return new ResourceLinkClaim(
            (string)$this->codec->encode($assessment->getId()),
            $assessment->getLtiAssessmentTitle(),
            $assessment->getLtiAssessmentText(),
        );
    }
}
