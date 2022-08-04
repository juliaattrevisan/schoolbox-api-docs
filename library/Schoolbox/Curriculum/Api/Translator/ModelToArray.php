<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Curriculum\Api\Translator;

use Schoolbox\Curriculum\Node\Model;

/**
 * Translator for translating a curriculum node model into an array, ready for
 * JSON encoding.
 */
class ModelToArray
{
    /**
     * Constructor
     *
     * @param int $ancestorCount   How many levels of ancestor get translated?
     *                             Any levels of ancestor above this get only a
     *                             stub record (with no further ancestor)
     *                             translated.
     * @param int $descendantCount How many levels of descendant get translated?
     *                             Any levels of descendant below this are not
     *                             translated at all.
     */
    public function __construct(
        private int $ancestorCount,
        private int $descendantCount,
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
            'learningArea'  => $node->getLearningArea()
                ? ['id' => $node->getLearningArea()->getId()]
                : null,
            'learningLevel' => $node->getLearningLevel()
                ? ['id' => $node->getLearningLevel()->getId()]
                : null,
            'order'         => $node->getOrder(),
            'createdAt'     => $node->getCreatedAt()->toRfc3339String(),
            'modifiedAt'    => $node->getModifiedAt()->toRfc3339String(),
        ];
    }
}
