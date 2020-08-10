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
    public const TABLE_NAME       = 'storage_transform';
    public const FIELD_HASH       = 'hash';
    public const FIELD_TRANSFORM  = 'transform';
    public const FIELD_SIZE       = 'size';

    /**
     * @inheritdoc
     */
    protected function mapping(): void
    {
        $this->hasTable(self::TABLE_NAME);
        $this->hasModel(Model::class);
        $this->uses(self::FIELD_HASH, self::TYPE_STRING);
        $this->uses(self::FIELD_TRANSFORM, self::TYPE_STRING);
        $this->uses(self::FIELD_SIZE, self::TYPE_INTEGER);
    }

    /**
     * @param string $hash
     * @param string $transform
     *
     * @return bool
     */
    public function checkIfRecordExists(string $hash, string $transform): bool
    {
        $columnLists = [
            self::FIELD_HASH        => $hash,
            self::FIELD_TRANSFORM   => $transform
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
     * @param string $transform
     * @param int    $size
     * @throws Exception
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    public function createRecord(string $hash, string $transform, int $size): void
    {
        $imageTransformation = $this->createModel()
                                    ->setHash($hash)
                                    ->setTransform($transform)
                                    ->setSize($size);
        $this->save($imageTransformation);
    }
}
