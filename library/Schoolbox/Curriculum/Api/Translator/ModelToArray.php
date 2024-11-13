<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Curriculum\Api\Translator;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\GetRouteIfAuthorized;
use Aura\Router\Generator;
use Schoolbox\Curriculum\Node\Model;
use Schoolbox\User;

/**
 * Translator for translating a curriculum node model into an array, ready for
 * JSON encoding.
 */
class ModelToArray
{
    use GetRouteIfAuthorized;

    /**
     * Constructor
     *
     * @param int $ancestorCount                      How many levels of ancestor get translated?
     *                                                Any levels of ancestor above this get only a stub record
     *                                                (with no further ancestor) translated.
     * @param int $descendantCount                    How many levels of descendant get translated?
     *                                                Any levels of descendant below this are not translated at all.
     * @param ?array<int, array<string, int>> $counts A set of usage counts for each node to be translated.
     */
    public function __construct(
        protected Generator $routeGenerator,
        protected Authorizer $authorizer,
        protected User $currentUser,
        private int $ancestorCount,
        private int $descendantCount,
        private ?array $counts,
    ) {
    }

    /**
     * @param Model $node
     * @return array
     */
    public function __invoke(Model $node): array
    {
        return $this->translate($node, $this->ancestorCount, $this->descendantCount);
    }

    /**
     * Internal method for translating this node, as well as recursively
     * translating its ancestors and descendants.
     *
     * @param Model $node
     * @param int   $ancestorCount   Number of levels of ancestors to translate.
     *                               If this is zero, translate only a stub
     *                               record for the parent.
     * @param int   $descendantCount Number of levels of descendants to
     *                               translate.
     *                               If this is zero, do not translate
     *                               descendants at all.
     * @return array
     */
    private function translate(Model $node, int $ancestorCount, int $descendantCount): array
    {
        $parent = null;
        if ($node->getParent()) {
            if ($ancestorCount > 0) {
                // Translate the parent, but never with its children
                $parent = $this->translate($node->getParent(), $ancestorCount - 1, 0);
            } else {
                // Translate a stub only for the parent
                $parent = ['id' => $node->getParent()->getId()];
            }
        }

        $children = null;
        if ($descendantCount > 0) {
            $children = [];
            foreach ($node->getChildren() as $child) {
                // Translate the children, but never with the full parent record
                $children[] = $this->translate($child, 0, $descendantCount - 1);
            }
        }

        return [
            'id'            => $node->getId(),
            'name'          => $node->getName(),
            'code'          => $node->getCode(),
            'reference'     => $node->getReference(),
            'description'   => $node->getDescription(),
            'enabled'       => $node->getEnabled(),
            'parent'        => $parent,
            'children'      => $children,
            'order'         => $node->getOrder(),
            'createdAt'     => $node->getCreatedAt()->toRfc3339String(),
            'modifiedAt'    => $node->getModifiedAt()->toRfc3339String(),
            '_usageCounts'  => $this->counts[$node->getId()] ?? null,
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
                'getExport' => $this->getRouteIfAuthorized(
                    'curriculum.getExport',
                    ['id' => $node->getId(), 'item' => $node],
                ),
            ],
        ];
    }
}
