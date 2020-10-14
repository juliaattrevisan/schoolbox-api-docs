<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Resource\Storage\Transform;

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
    public const TABLE_NAME      = 'storage_transform';
    public const FIELD_HASH      = 'hash';
    public const FIELD_CACHE_KEY = 'transform';
    public const FIELD_SIZE      = 'size';

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
     * @param string $hash
     * @param string $cacheKey
     * @param int    $size
     * @throws Exception
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    public function createRecord(string $hash, string $cacheKey, int $size): void
    {
        $imageTransformation = $this->createModel()
                                    ->setHash($hash)
                                    ->setCacheKey($cacheKey)
                                    ->setSize($size);
        $this->save($imageTransformation);
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
            self::FIELD_HASH => $hash,
            self::FIELD_CACHE_KEY=> $cacheKey,
        ])->first();
    }
}
