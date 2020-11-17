<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Badge\Translator;

use Alaress\HTMLPurifier\IPurifier;
use Error;
use Schoolbox\Learning\Badge\Model;

/**
 * Translator for converting a badge instance to an array representation, for
 * outputting in a JSON response.
 *
 * TODO currently this is mostly a copypaste of the evidence translator.
 *      There must be something which can be extracted out?
 */
class ModelToArray
{
    /**
     * Translator for converting a user within the evidence model.
     *
     * (callable isn't a valid typedef)
     *
     * @var callable
     */
    private $userTranslator;


    /**
     * Translator for converting a storage object.
     *
     * (callable isn't a valid typedef)
     *
     * @var callable
     */
    private $storageTranslator;


    /**
     * HTMLPurifier instance, or an adapter thereof.
     *
     * Required for purifying the evidence's body (if present).
     */
    private IPurifier $purifier;


    /**
     * Constructor
     *
     * @param callable  $userTranslator
     * @param callable  $storageTranslator
     * @param IPurifier $purifier
     */
    public function __construct(callable $userTranslator, callable $storageTranslator, IPurifier $purifier)
    {
        $this->userTranslator    = $userTranslator;
        $this->storageTranslator = $storageTranslator;
        $this->purifier          = $purifier;
    }


    /**
     * @param Model $model
     * @return array
     */
    public function __invoke(Model $model): array
    {
        // Users cannot be null in persisted entities.
        // Allow them to be null when translating though?
        try {
            $createdBy = call_user_func($this->userTranslator, $model->getCreatedBy());
        } catch (Error $e) {
            $createdBy = null;
        }
        try {
            $updatedBy = call_user_func($this->userTranslator, $model->getUpdatedBy());
        } catch (Error $e) {
            $updatedBy = null;
        }

        return [
            'id'              => $model->getId(),
            'createdAt'       => $model->getCreatedAt() ? $model->getCreatedAt()->getUsingFormat(DATE_ATOM) : null,
            'createdBy'       => $createdBy,
            'updatedAt'       => $model->getUpdatedAt() ? $model->getUpdatedAt()->getUsingFormat(DATE_ATOM) : null,
            'updatedBy'       => $updatedBy,
            'name'            => $model->getName(),
            'body'            => $this->purifier->purify($model->getBody()),
            'image'           => $model->getImage()
                ? call_user_func($this->storageTranslator, $model->getImage())
                : null,

            // TODO translate category, curriculum nodes
            'category'        => null,
            'curriculumNodes' => null,
        ];
    }
}
