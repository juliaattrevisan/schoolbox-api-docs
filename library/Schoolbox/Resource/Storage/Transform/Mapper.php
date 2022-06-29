<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\Transform;

use InvalidArgumentException;
use PDOException;
use Schoolbox\Mapper\Exception;
use UnexpectedValueException;

/**
 * Class Mapper
 * @package Schoolbox\Resource\Storage\Transform
 * @method Model createModel($idValue = null)
 */
class Mapper extends \Schoolbox\Mapper
{

    // Table definition
    public const TABLE_NAME       = 'storage_transform';
    public const FIELD_HASH       = 'hash';
    public const FIELD_CACHE_KEY  = 'transform';
    public const FIELD_SIZE       = 'size';
    public const FIELD_STATE      = 'state';
    public const FIELD_UPDATED_AT = 'updated_at';
    public const FIELD_FORMAT    = 'format';

    /**
     * @inheritdoc
     */
    protected function mapping(): void
    {
        $this->hasTable(self::TABLE_NAME);
        $this->hasModel(Model::class);
        $this->uses(self::FIELD_HASH, self::TYPE_STRING);
        $this->uses(self::FIELD_CACHE_KEY, self::TYPE_STRING, null, 'cacheKey');
        $this->uses(self::FIELD_SIZE, self::TYPE_INTEGER);
        $this->uses(self::FIELD_STATE, self::TYPE_STRING, self::COLUMN_ENUM, null, Model::STATES);
        $this->uses(self::FIELD_UPDATED_AT, self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses(self::FIELD_FORMAT, self::TYPE_STRING);

        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'verifySizeAndStateCombo']);
    }


    /**
     * PreSave callback to ensure we don't save a transform where:
     * * size is NULL && state is DONE, or
     * * size is !NULL && state is !DONE
     *
     * @param Model $model
     * @return bool
     * @throws Exception\InsufficientHydrationException
     * @throws InvalidArgumentException
     */
    protected function verifySizeAndStateCombo(Model $model): bool
    {
        if (
            ($model->getSize() === null && $model->isStateDone()) ||
            ($model->getSize() !== null && !$model->isStateDone())
        ) {
            throw new InvalidArgumentException(
                "Invalid size/state combination. Size: {$model->getSize()}, State: {$model->getState()}"
            );
        }
        return true;
    }

    /**
     * @param string $hash
     * @param string $cacheKey
     *
     * @return bool
     */
    public function checkIfRecordExists(string $hash, string $cacheKey): bool
    {
        $columnLists = [
            self::FIELD_HASH        => $hash,
            self::FIELD_CACHE_KEY => $cacheKey
        ];

        if ($this->countByColumnList($columnLists)) {
            return true;
        }

        return false;
    }

    /**
     * Creates a new Transform record in database
     *
     * @param string   $hash
     * @param string   $cacheKey
     * @param null|int $size
     * @param string   $state
     * @param string $format
     * @return Model
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    public function createRecord(string $hash, string $cacheKey, ?int $size, string $state, string $format): Model
    {
        $imageTransformation = $this->createModel()
                                    ->setHash($hash)
                                    ->setCacheKey($cacheKey)
                                    ->setSize($size)
                                    ->setState($state)
                                    ->setFormat($format);
        $this->save($imageTransformation);
        return $imageTransformation;
    }

    /**
     * Returns the model for the specified hash + cache key, if found
     *
     * @param string $hash
     * @param string $cacheKey
     * @return Model|null
     */
    public function findByHashAndCacheKey(string $hash, string $cacheKey): ?Model
    {
        return $this->findByColumnList([
            self::FIELD_HASH      => $hash,
            self::FIELD_CACHE_KEY => $cacheKey,
        ])->first();
    }
}
