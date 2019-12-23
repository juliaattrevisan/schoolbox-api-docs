<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace External\Canvas\Sync\ImportStage\Traits;

use Alaress\Database;
use PDOException;
use Schoolbox\IMapper;

/**
 * Enables Model save via a mapper without invoking a transaction or calling pre/post save hooks
 */
trait ModelSaveTrait
{
    /**
     * Saves (either INSERT or UPDATE) a model's attributes.
     * - Does not save joins.
     * - Populates the model's identifier if it has one, and this is an INSERT
     *
     * We need this, rather than the Mapper::save() method, because we don't want to use a transaction, and
     * we want to by-pass pre/post save hooks, eg auto-creation of Assessment's dynamic plan.
     *
     * @param IMapper $mapper
     * @param         $model
     * @return mixed
     * @throws Database\Exception
     */
    private function saveModel(IMapper $mapper, $model)
    {
        $hasId     = (bool)$mapper->getIdentifier();
        $currentId = $hasId ? $mapper->getModelProperty($model, $mapper->getIdentifierProperty()) : null;

        // These mapper methods are not public, so need to use a closure to bypass encapsulation.
        $collection       = $mapper->getModelAsCollection($model);
        $populateFunction = function () use ($collection) {
            /** @noinspection PhpUndefinedMethodInspection */
            return $this->populateUuids($this->populateSaveTimestamps($collection));
        };
        $populateFunction->call($mapper);

        $statement = $mapper->getDatabase()->prepare("
            INSERT INTO `{$mapper->getTable()}` ({$mapper->getColumnString()})
            VALUES ({$mapper->getPlaceholderString()})
            ON DUPLICATE KEY UPDATE {$mapper->getUpdateString()};
        ");
        try {
            $statement->execute($mapper->dehydrate($model));
        } catch (PDOException $e) {
            throw new Database\Exception("Could not save model", 0, $e);
        }


        if ($hasId && !$currentId) {
            $mapper->setModelProperty($model, $mapper->getIdentifierProperty(), $mapper->getDatabase()->lastInsertId());
        }
        return $model;
    }
}
