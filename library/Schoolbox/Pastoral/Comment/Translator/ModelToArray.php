<?php
/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\Comment\Translator;

use Schoolbox\Pastoral\Comment\Model;

/**
 * Model-to-array translator for pastoral comments.
 */
class ModelToArray
{
    /**
     * User translator, for translating the comment's owner.
     *
     * @var callable
     */
    private $userTranslator;

    /**
     * Constructor
     *
     * @param callable $userTranslator
     */
    public function __construct(callable $userTranslator)
    {
        $this->userTranslator = $userTranslator;
    }

    /**
     * Convert this model to an array.
     *
     * @param Model $model
     * @return array
     */
    public function __invoke(Model $model): array
    {
        return [
            'id'           => $model->getId(),
            'pastoral'     => [
                'id'      => $model->getPastoral()->getId(),
                'owner'   => call_user_func($this->userTranslator, $model->getPastoral()->getOwner()),
                'student' => call_user_func($this->userTranslator, $model->getPastoral()->getStudent()),
            ],
            'owner'        => call_user_func($this->userTranslator, $model->getOwner()),
            'body'         => $model->getBody(),
            'externalCode' => $model->getExternalCode(),
            'createdAt'    => $model->getCreatedAt() && !$model->getCreatedAt()->isNull()
                ? $model->getCreatedAt()->getUsingFormat(DATE_ATOM)
                : null,
            'updatedAt'    => $model->getUpdatedAt() && !$model->getUpdatedAt()->isNull()
                ? $model->getUpdatedAt()->getUsingFormat(DATE_ATOM)
                : null,
            'private'      => $model->getPrivate(),
        ];
    }
}
