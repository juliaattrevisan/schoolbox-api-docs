<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Platform\LaunchPresentation;

use Aura\Router\Generator;
use OAT\Library\Lti1p3Core\Message\Payload\Claim\LaunchPresentationClaim;
use Psr\Http\Message\UriInterface;
use Schoolbox\Learning\Assessment\Lti\Model;
use Schoolbox\Permission\Access\Access;
use Schoolbox\User;

/**
 * Factory for claims about the launch of the resource link.
 */
class ClaimFactory
{
    /**
     * Constructor
     *
     * @param Access       $access         Used to determine whether a user has instructor access to an assessment's folder.
     * @param UriInterface $baseUrl        Base URL for this instance.
     * @param Generator    $routeGenerator Used to build a path to a return URL.
     */
    public function __construct(
        private Access $access,
        private UriInterface $baseUrl,
        private Generator $routeGenerator,
    ) {
    }

    /**
     * Build the launch presentation claim for the given assessment and user.
     *
     * @param Model    $assessment The assessment to view.
     * @param User     $user       The user account for whom the assessment is
     *                             being viewed: this may be an instructor or
     *                             a student.
     * @param int|null $height     If set, add this height to the launch
     *                             presentation claim.
     * @return LaunchPresentationClaim
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \Schoolbox\Mapper\Exception
     */
    public function buildForAssessmentAndUser(Model $assessment, User $user, ?int $height): LaunchPresentationClaim
    {
        $returnUrl = $this->access->isInstructor($user, $assessment->getBaseContext()->getFolder())
            ? $this->routeGenerator->generate(
                'learningAssessment.getLaunchAssessmentAsInstructorReturn',
                [
                    'assessmentId' => $assessment->getId(),
                    'target'       => $assessment->getLtiTool()->getTarget(),
                ]
            )
            : $this->routeGenerator->generate(
                'learningAssessment.getLaunchAssessmentAsStudentReturn',
                [
                    'assessmentId' => $assessment->getId(),
                    'studentId'    => $user->getId(),
                    'target'       => $assessment->getLtiTool()->getTarget(),
                ]
            );
        return new LaunchPresentationClaim(
            $assessment->getLtiTool()->getTarget(),
            isset($height) ? (string)$height : null,
            null,
            $this->baseUrl->withPath($returnUrl)->__toString(),
        );
    }
}
