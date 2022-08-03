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
     * @param Model $node
     * @return array
     */
    public function __invoke(Model $node): array
    {
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
            'children'      => [],
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
