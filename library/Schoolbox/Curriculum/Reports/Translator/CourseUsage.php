<?php

namespace Schoolbox\Curriculum\Reports\Translator;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\GetRouteIfAuthorized;
use Aura\Router\Generator;
use Schoolbox\Curriculum\Node\Model as NodeModel;
use Schoolbox\Curriculum\Reports\Translator\UnitAssessmentUsage as UnitAssessmentUsageTranslator;
use Schoolbox\Learning\Course\DataModel\Specification\Model;
use Schoolbox\User;

class CourseUsage
{
    use GetRouteIfAuthorized;

    /**
     * Constructor
     *
     * @param ?array $counts A set of usage counts for each node to be translated.
     */
    public function __construct(
        protected Generator $routeGenerator,
        protected Authorizer $authorizer,
        protected User $currentUser,
        private UnitAssessmentUsageTranslator $unitAssessmentUsageUsageTranslator,
        private array $courseUnitSpec,
        private ?array $counts,
    ) {
    }

    /**
     * @param Model $model
     * @return array
     */
    public function __invoke(Model $model, NodeModel $node): array
    {
        return $this->translate($model, $node);
    }

    private function translate(Model $model, NodeModel $node): array
    {

        $courseCount = $this->counts['courseSpecification'][$node->getId()][$model->getId()] ?? 0;

        return [
            'id'    => $model->getId(),
            'name'  => $model->getTitle(),
            'usage'  => [
                'course' => [
                    'count' => $courseCount
                ]
            ],
            'units' => [
                array_map(
                    fn($unit) =>
                    call_user_func(
                        $this->unitAssessmentUsageUsageTranslator,
                        $unit,
                        $node
                    ),
                    $this->courseUnitSpec[$model->getId()]->getAll()
                )
            ]
        ];
    }
}
