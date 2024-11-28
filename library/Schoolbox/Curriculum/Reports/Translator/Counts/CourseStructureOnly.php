<?php

namespace Schoolbox\Curriculum\Reports\Translator\Counts;

use Alaress\Authorizer\Authorizer;
use Aura\Router\Generator;
use Schoolbox\Learning\Course\DataModel\Specification\Model as Model;
use Schoolbox\Resource\Folder\Translator\IModelToArray;
use Schoolbox\User;
use Schoolbox\Curriculum\Reports\Helper;

/**
 *  This translator builds the response for the details and structure of the
 *  nodes as well as the details of the courses and units that it associated with
 */
class CourseStructureOnly
{
    /**
     * Constructor
     *
     * @param Generator     $routeGenerator
     * @param Authorizer    $authorizer
     * @param User          $currentUser
     * @param Helper        $helper
     * @param array         $courseUnitSpec
     * @param IModelToArray $folderTranslator
     */
    public function __construct(
        protected Generator $routeGenerator,
        protected Authorizer $authorizer,
        protected User $currentUser,
        private Helper $helper,
        private array $courseUnitSpec,
        private IModelToArray $folderTranslator,
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
            'folder' => call_user_func($this->folderTranslator, $model->getFolder()),
            'units' => $this->helper->filterUserPermission(
                $this->courseUnitSpec[$model->getId()],
                $this->currentUser
            )?->map(
                fn($unit) =>
                [
                    'id' => $unit->getFolder()->getId(),
                    'name' => $unit->getTitle(),
                    'folder' => call_user_func($this->folderTranslator, $unit->getFolder()),
                ],
            )
        ];
    }
}
