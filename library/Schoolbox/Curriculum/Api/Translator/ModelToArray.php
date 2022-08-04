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
     * @param int $descendantCount How many levels of descendant get translated?
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
        // Determine how to hydrate parents and children.
        // Children: don't have parents fully hydrated (just the ID)
        // Parents: don't have children hydrated at all
        return [
            'id'            => $node->getId(),
            'name'          => $node->getName(),
            'code'          => $node->getCode(),
            'reference'     => $node->getReference(),
            'description'   => $node->getDescription(),
            'enabled'       => $node->getEnabled(),
            'parent'        => $node->getParent()
                ? ['id' => $node->getParent()->getId()]
                : null,
            'children'      => $node->getChildren()->map($this),
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
