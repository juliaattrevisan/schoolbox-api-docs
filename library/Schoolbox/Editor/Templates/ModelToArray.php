<?php

/**
 * @author      Michael Heydon <michael.heydon@schoolbox.education>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Editor\Templates;

use Schoolbox\UserMapper;

/**
 * Translator for translating a template model into an array, ready for JSON
 * encoding.
 */
class ModelToArray
{
    /**
     * Constructor
     *
     * @param \Schoolbox\User\Translator\Author\ModelToArray $userTranslator
     * @param \Schoolbox\UserMapper $userMapper
     */
    public function __construct(
        private \Schoolbox\User\Translator\Author\ModelToArray $userTranslator,
        private \Schoolbox\UserMapper $userMapper,
    ) {
    }

    /**
     * @param Model $model

     * @return array
     */
    public function __invoke(Model $model): array
    {
        $createdBy = null;
        if ($model->getCreatedBy()) {
            try {
                $createdBy = $this->userMapper->findById($model->getCreatedBy());
                $createdBy = call_user_func($this->userTranslator, $createdBy);
            } catch (\Exception $e) {
                // User not found or other error
                // noop
            }
        }

        return [
            'id' => $model->getId(),
            'title' => $model->getTitle(),
            'description' => $model->getDescription(),
            'body' => $model->getBody(),
            'createdAt' => $model->getCreatedAt(),
            'createdBy' => $createdBy,
        ];
    }
}
