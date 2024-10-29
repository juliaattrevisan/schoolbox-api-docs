<?php

namespace Schoolbox\Curriculum\Reports\Translator;

use Alaress\Authorizer\Authorizer;
use Aura\Router\Generator;
use Schoolbox\Learning\Course\DataModel\Specification\Model as Model;
use Schoolbox\User;

/**
 *  This translator builds the response for the details and structure of the
 *  nodes as well as the details of the courses and units that it associated with
 */
class CourseStructureOnly
{
    /**
     * Constructor
     *
     * @param Generator  $routeGenerator
     * @param Authorizer $authorizer
     * @param User       $currentUser
     * @param array      $courseUnitSpec
     */
    public function __construct(
        protected Generator $routeGenerator,
        protected Authorizer $authorizer,
        protected User $currentUser,
        private array $courseUnitSpec,
    ) {
    }

    /**
     * @param Model $model
     * @return array
     */
    public function __invoke(Model $model): array
    {
        return $this->translate($model);
    }

    private function translate(Model $model): array
    {
        return [
            'id' => $model->getId(),
            'name' => $model->getTitle(),
            'units' => array_map(
                fn($unit) =>
                [
                    'id' => $unit->getFolder()->getId(),
                    'name' => $unit->getTitle(),
                ],
                $this->courseUnitSpec[$model->getId()]->getAll()
            ),
        ];
    }
}
