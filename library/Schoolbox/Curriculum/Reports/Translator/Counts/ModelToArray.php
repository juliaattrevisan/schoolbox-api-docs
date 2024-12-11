<?php

namespace Schoolbox\Curriculum\Reports\Translator\Counts;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\GetRouteIfAuthorized;
use Aura\Router\Generator;
use Schoolbox\Collection;
use Schoolbox\Curriculum\Node\Model;
use Schoolbox\User;
use Schoolbox\Curriculum\Reports\Translator\Counts\CourseUsage as courseUsageTranslator;

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
     * @param array                 $counts         A set of usage counts for each node to be translated.
     * @param bool                  $streamChildren Are children output in a generator to be streamed, or in an array
     *                                              structure for output via JSON?
     */
    public function __construct(
        protected Generator $routeGenerator,
        protected Authorizer $authorizer,
        protected User $currentUser,
        private CourseUsageTranslator $courseUsageTranslator,
        private Collection $courseSpec,
        private array $counts,
        private bool $streamChildren,
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
        // Output children either as a generator for memory efficiency,
        // or as an array for json_encode compatibility.
        $children = $this->streamChildren
            ? $this->translateChildrenAsGenerator($node)
            : $this->translateChildrenAsArray($node);

        return [
            'id'       => $node->getId(),
            'name'     => $node->getName(),
            'code'     => $node->getCode(),
            'usage'    => [
                'courses' =>
                    $this->courseSpec->map(
                        function ($course) use ($node) {
                            return call_user_func($this->courseUsageTranslator, $course, $node, $this->counts);
                        }
                    )
            ],
            'children' => $children,
            '_links'   => [
                'read'     => $this->getRouteIfAuthorized(
                    'curriculumApi.getNode',
                    ['id' => $node->getId(), 'node' => $node],
                ),
                'update'   => $this->getRouteIfAuthorized(
                    'curriculumApi.putNode',
                    ['id' => $node->getId(), 'node' => $node],
                ),
                'delete'   => $this->getRouteIfAuthorized(
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

    /**
     * Translate the node's children as an array, calling `translate`
     * immediately.
     *
     * @param Model $node
     * @return array|null
     */
    private function translateChildrenAsArray(Model $node): ?array
    {
        $children = null;
        if ($node->getChildren()->count() > 0) {
            $children = [];
            foreach ($node->getChildren() as $child) {
                // Translate the children, but never with the full parent record
                $children[] = $this->translate($child);
            }
        }
        return $children;
    }

    /**
     * Translate the node's children as a generator, calling `translate`
     * only when the generator is iterated.
     *
     * @param Model $node
     * @return iterable|null
     */
    private function translateChildrenAsGenerator(Model $node): ?iterable
    {
        if ($node->getChildren()->count() === 0) {
            return null;
        }
        foreach ($node->getChildren() as $child) {
            yield $this->translate($child);
        }
    }
}
