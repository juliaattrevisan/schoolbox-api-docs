<?php

namespace Schoolbox\Curriculum\Reports\Translator;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\GetRouteIfAuthorized;
use Aura\Router\Generator;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as Model;
use Schoolbox\Curriculum\Node\Model as CurriculumNodeModel;
use Schoolbox\User;

class UnitAssessmentUsage
{
    use GetRouteIfAuthorized;

    /**
     * Constructor
     *
     * @param Generator     $routeGenerator
     * @param Authorizer    $authorizer
     * @param User          $currentUser
     * @param ?array        $counts A set of usage counts for each node to be translated.
     */
    public function __construct(
        protected Generator $routeGenerator,
        protected Authorizer $authorizer,
        protected User $currentUser,
        private ?array $counts,
    ) {
    }

    /**
     * @param Model               $model
     * @param CurriculumNodeModel $node
     * @return array
     */
    public function __invoke(Model $model, CurriculumNodeModel $node): array
    {
        return $this->translate($model, $node);
    }

    private function translate(Model $model, CurriculumNodeModel $node): array
    {
        $unitCount =
            $this->counts['unit'][$node->getId()][$model->getCourse()->getId()][$model->getFolder()->getId()] ?? 0;
        $activityCount =
            ($this->counts['task'][$node->getId()][$model->getCourse()->getId()][$model->getFolder()->getId()] ?? 0) +
            ($this->counts['quiz'][$node->getId()][$model->getCourse()->getId()][$model->getFolder()->getId()] ?? 0) +
            ($this->counts['LTI'][$node->getId()][$model->getCourse()->getId()][$model->getFolder()->getId()] ?? 0) +
            ($this->counts['dueWork'][$node->getId()][$model->getCourse()->getId()][$model->getFolder()->getId()] ?? 0) +
            ($this->counts['project'][$node->getId()][$model->getCourse()->getId()][$model->getFolder()->getId()] ?? 0);
        $lessonPlanCount =
            ($this->counts['lessonPlan'][$node->getId()][$model->getCourse()->getId()][$model->getFolder()->getId()] ?? 0);

        return [
            'id'    => $model->getFolder()->getId(),
            'name'  => $model->getTitle(),
            'usage'  => [
                'unit' => [
                    'count' => $unitCount
                ],
                'activity' => [
                    'count' => $activityCount
                ],
                'lessonPlan' => [
                    'count' => $lessonPlanCount
                ]
            ]
        ];
    }
}
