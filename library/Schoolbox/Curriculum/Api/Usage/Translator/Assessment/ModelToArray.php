<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Curriculum\Api\Usage\Translator\Assessment;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\GetRouteIfAuthorized;
use Aura\Router\Router;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\User;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpecification;

/**
 * Curriculum usage specific translator for translating assessments to an array.
 *
 * Contains only what is needed (ID, title, unit, links).
 */
class ModelToArray
{
    use GetRouteIfAuthorized;

    /**
     * Constructor
     *
     * @param Router                                    $router
     * @param Authorizer                                $authorizer
     * @param User                                      $currentUser
     * @param UnitSpecification\Translator\ModelToArray $unitTranslator
     */
    public function __construct(
        protected Router $router,
        protected Authorizer $authorizer,
        protected User $currentUser,
        private UnitSpecification\Translator\ModelToArray $unitTranslator,
    ) {
    }

    /**
     * @param IAssessable $assessment
     * @return array
     */
    public function __invoke(IAssessable $assessment): array
    {
        $unit = $assessment->getBaseContext() instanceof UnitSpecification\Model
            ? $assessment->getBaseContext()
            : null;
        return [
            'id'     => $assessment->getId(),
            'title'  => $assessment->getTitle(),
            'type'   => $assessment->getFamilyName(),
            'unit'   => $unit ? call_user_func($this->unitTranslator, $unit) : null,
            '_links' => [
                'view' => $this->getRouteIfAuthorized(
                    'learningAssessment.getUpdateAssessmentForm',
                    ['assessment' => $assessment, 'assessmentId' => $assessment->getId()],
                ),
            ],
        ];
    }
}
