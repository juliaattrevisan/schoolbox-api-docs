<?php

namespace Schoolbox\Curriculum\Reports\Translator;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\GetRouteIfAuthorized;
use Aura\Router\Generator;
use Schoolbox\Collection;
use Schoolbox\Curriculum\Node\Model;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper;
use Schoolbox\User;
use Schoolbox\Curriculum\Reports\Translator\CourseUsage as courseUsageTranslator;

/**
 * Curriculum node model to array translator for curriculum coverage report
 */
class ModelToArray
{
    use GetRouteIfAuthorized;

    /**
     * Constructor
     *
     * @param Generator             $routeGenerator
     * @param Authorizer            $authorizer
     * @param User                  $currentUser
     * @param CourseUsageTranslator $courseUsageTranslator
     * @param Collection            $courseSpec
     * @param array                 $counts A set of usage counts for each node to be translated.
     */
    public function __construct(
        protected Generator $routeGenerator,
        protected Authorizer $authorizer,
        protected User $currentUser,
        private CourseUsageTranslator $courseUsageTranslator,
        private Collection $courseSpec,
        private array $counts
    ) {
    }

    /**
     * @param Model $node
     * @return array
     */
    public function __invoke(Model $node): array
    {
        return $this->translate($node);
    }

    /**
     * Internal method for translating this node, as well as recursively
     * translating its descendants, and it will search for all descendants in the tree
     *
     * @param Model $node
     * @return array
     */
    private function translate(Model $node): array
    {
        $children = null;
        //Find all descendants
        if ($node->getChildren()->count() > 0) {
            $children = [];
            foreach ($node->getChildren() as $child) {
                // Translate the children, but never with the full parent record
                $children[] = $this->translate($child);
            }
        }

        return [
            'id'            => $node->getId(),
            'name'          => $node->getName(),
            'code'          => $node->getCode(),
            'usage'         => [
                'courses' =>
                    array_map(
                        fn($course) =>
                        call_user_func(
                            $this->courseUsageTranslator,
                            $course,
                            $node,
                            $this->counts
                        ),
                        $this->courseSpec->getAll()
                    )
            ],
            'children'      => $children,
            '_links'        => [
                'read' => $this->getRouteIfAuthorized(
                    'curriculumApi.getNode',
                    ['id' => $node->getId(), 'node' => $node],
                ),
                'update' => $this->getRouteIfAuthorized(
                    'curriculumApi.putNode',
                    ['id' => $node->getId(), 'node' => $node],
                ),
                'delete' => $this->getRouteIfAuthorized(
                    'curriculumApi.deleteNode',
                    ['id' => $node->getId(), 'node' => $node],
                ),
                'getUsage' => $this->getRouteIfAuthorized(
                    'curriculumApiUsage.getNodeUsage',
                    ['id' => $node->getId(), 'node' => $node],
                ),
            ],
        ];
    }
}
