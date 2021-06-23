<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Badge\Translator;

use Alaress\HTMLPurifier\IPurifier;
use Error;
use Schoolbox\Curriculum\Node;
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
     * Translator for generating urls relevant to an badge
     *
     * (callable isn't a valid typedef)
     *
     * @var callable
     */
    private $linkTranslator;


    /**
     * Constructor
     *
     * @param callable  $userTranslator
     * @param callable  $storageTranslator
     * @param IPurifier $purifier
     */
    public function __construct(callable $userTranslator, callable $storageTranslator, IPurifier $purifier, callable $linkTranslator)
    {
        $this->userTranslator    = $userTranslator;
        $this->storageTranslator = $storageTranslator;
        $this->purifier          = $purifier;
        $this->linkTranslator    = $linkTranslator;
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

        // Category and nodes should eventually have separate translators.
        // For now, they just have something inbuilt here.
        try {
            $image = $model->getImage();
        } catch (Error $e) {
            $image = null;
        }

        try {
            $category = $model->getCategory();
        } catch (Error $e) {
            $category = null;
        }

        try {
            $curriculumNodes = $model->getCurriculumNodes();
        } catch (Error $e) {
            $curriculumNodes = null;
        }


        return [
            'id'                => $model->getId(),
            'createdAt'         => $model->getCreatedAt() && !$model->getCreatedAt()->isNull()
                ? $model->getCreatedAt()->getUsingFormat(DATE_ATOM)
                : null,
            'createdBy'         => $createdBy,
            'updatedAt'         => $model->getUpdatedAt() && !$model->getUpdatedAt()->isNull()
                ? $model->getUpdatedAt()->getUsingFormat(DATE_ATOM)
                : null,
            'updatedBy'         => $updatedBy,
            'name'              => $model->getName(),
            'body'              => $this->purifier->purify($model->getBody()),
            'image'             => $image
                ? call_user_func($this->storageTranslator, $model->getImage())
                : null,
            'icon'              => $model->getIcon(),
            'allowAsGoal'       => $model->getAllowAsGoal(),
            'category'          => $category
                ? [
                    'id'   => $category->getId(),
                    'name' => $category->getName(),
                ]
                : null,
            'achievementExpiry' => $model->getAchievementExpiry(),
            'curriculumNodes'   => $curriculumNodes
                ? $curriculumNodes
                    ->map(
                        static function (Node\Model $node) {
                            return [
                                'id'   => $node->getId(),
                                'name' => $node->getName(),
                                'code' => $node->getCode(),
                            ];
                        }
                    )
                    ->reindex()
                : null,

            // Tags don't exist yet, they will be added soon though
            'tags'              => null,

            /* Assign a variety of useful urls about this badge
             */
            '_links'            => call_user_func($this->linkTranslator, $model),
        ];
    }
}
