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
        $parent = null;
        if ($node->getParent()) {
            if ($this->ancestorCount > 0) {
                $parentTranslator = new self($this->ancestorCount - 1, 0);
                $parent           = $parentTranslator($node->getParent());
            } else {
                $parent           = ['id' => $node->getParent()->getId()];
            }
        }
        $children = null;
        if ($this->descendantCount > 0) {
            $childTranslator = new self(0, $this->descendantCount - 1);
            $children        = $node->getChildren()->map($childTranslator);
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
