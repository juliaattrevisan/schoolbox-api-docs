<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Resource\Storage\Transform;

use League\Flysystem;
use PDOException;
use Schoolbox\Collection;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception;
use Schoolbox\Resource\FilesystemFactory;
use Schoolbox\Resource\Storage;
use UnexpectedValueException;

/**
 * Class Mapper
 * @package Schoolbox\Resource\Storage\Transform
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
        $this->uses(self::FIELD_TRANSFORM, self::TYPE_STRING);
        $this->uses(self::FIELD_SIZE, self::TYPE_INTEGER);
        $this->joins(self::FIELD_HASH, \Schoolbox\Resource\Storage\Mapper::class, 'hash', null, null, 'storage', 'hash');
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
     * @param Storage\Model $storage
     * @param string        $transform
     * @throws Exception
     * @throws Flysystem\FileNotFoundException
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    public function createRecord(\Schoolbox\Resource\Storage\Model $storage, string $transform): void
    {
        $imageTransformation = $this->createModel()
                                    ->setStorage($storage)
                                    ->setTransform($transform)
                                    ->setSize($storage->getSize());
        $this->save($imageTransformation);
    }

    /**
     * @param \Schoolbox\Resource\Storage\Model $storageModel
     * @param string|null                       $transformSize
     *
     * @return GenericCollection
     */
    public function findByStorageModel(\Schoolbox\Resource\Storage\Model $storageModel, ?string $transformSize = null): GenericCollection
    {
        $config = $this->getConfigLoader();
        $collection = new GenericCollection(Model::class);

        if (!$config->get(ConfigMapper::NAME_IMAGE_S3_PRESIGNED_URL_ENABLED) ||
            $config->get(ConfigMapper::NAME_OTFS_ADAPTER) !== FilesystemFactory::FILE_SYSTEM_ADAPTER_AWS_S3
        ) {
            return $collection;
        }

        $columnLists = [
            self::FIELD_HASH        => $storageModel->getHash(),
        ];

        if ($transformSize !== null) {
            $columnLists += [self::FIELD_TRANSFORM   => $transformSize];
        }

        return $this->findByColumnList($columnLists);
    }

    /**
     * @param GenericCollection $collections
     *
     * @return Collection|GenericCollection|GenericCollection[]
     */
    public function findMultipleByCollection(GenericCollection $collections)
    {
        $config = $this->getConfigLoader();

        // Short circuit.. just return the portraits without the transformations
        if (!$config->get(ConfigMapper::NAME_IMAGE_S3_PRESIGNED_URL_ENABLED) ||
            $config->get(ConfigMapper::NAME_OTFS_ADAPTER) !== FilesystemFactory::FILE_SYSTEM_ADAPTER_AWS_S3 ||
            !$collections->count()
        ) {
            return $collections;
        }

        return $this->findByColumn(
            self::FIELD_HASH,
            array_unique($collections->getPropertyValues(self::FIELD_HASH))
        )->groupBy(self::FIELD_HASH);
    }
}
