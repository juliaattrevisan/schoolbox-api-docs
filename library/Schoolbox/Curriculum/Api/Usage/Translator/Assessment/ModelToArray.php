<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Curriculum\Api\Usage\Translator\Assessment;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\GetRouteIfAuthorized;
use Aura\Router\Generator;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpecification;
use Schoolbox\User;

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
     * @param Generator                                 $routeGenerator
     * @param Authorizer                                $authorizer
     * @param User                                      $currentUser
     * @param UnitSpecification\Translator\ModelToArray $unitTranslator
     */
    public function __construct(
        protected Generator $routeGenerator,
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
        return $this->translate($assessment, true, true);
    }

    /**
     * Internal translation function: recursively translates `$assessment`
     * project if it exists, but doesn't include the unit or project fields in
     * that translation.
     *
     * @param IAssessable $assessment
     * @param bool        $includeUnit
     * @param bool        $includeProject
     * @return array
     */
    private function translate(IAssessable $assessment, bool $includeUnit, bool $includeProject): array
    {
        $translation = [
            'id'    => $assessment->getId(),
            'title' => $assessment->getTitle(),
            'type'  => $assessment->getFamilyName(),
        ];

        if ($includeUnit) {
            $unit        = null;
            $baseContext = $assessment->getBaseContext();
            if ($baseContext instanceof UnitSpecification\Model) {
                $unit = call_user_func($this->unitTranslator, $assessment->getBaseContext());
            }
            $translation['unit'] = $unit;
        }

        if ($includeProject) {
            $project = null;
            $context = $assessment->getContext();
            if ($context instanceof Project) {
                $project = $this->translate($context, false, false);
            }
            $translation['project'] = $project;
        }

        $translation['_links'] = [
            'view' => $this->getRouteIfAuthorized(
                'learningAssessment.getUpdateAssessmentForm',
                ['assessment' => $assessment, 'assessmentId' => $assessment->getId()],
            ),
        ];

        return $translation;
    }
}
