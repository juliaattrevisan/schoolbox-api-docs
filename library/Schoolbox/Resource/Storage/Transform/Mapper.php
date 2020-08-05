<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Resource\Storage\Transform;

use Alaress_Date_Format;
use Schoolbox\Collection;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception;
use Schoolbox\Resource\FilesystemFactory;
use Schoolbox\Resource\Storage\S3\PreSignedUrlService;

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
     * @param string $hash
     * @param string $transform
     * @param int    $fileSize
     *
     * @throws Exception
     */
    public function createRecord(string $hash, string $transform, int $fileSize): void
    {
        $imageTransformation = new Model();
        $imageTransformation->setHash($hash)
            ->setTransform($transform)
            ->setSize($fileSize);

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

        $presignedUrlService = new PreSignedUrlService($config);

        $columnLists = [
            self::FIELD_HASH        => $storageModel->getHash(),
        ];

        if ($transformSize !== null) {
            $columnLists += [self::FIELD_TRANSFORM   => $transformSize];
        }

        $transformations = $this->findByColumnList($columnLists);

        /** @var Model $transform */
        foreach ($transformations as $transform) {
            $presignedUrl = $presignedUrlService->generate(
                $storageModel,
                Alaress_Date_Format::now()->addSeconds($config->get(ConfigMapper::NAME_BROWSER_CACHE_IMAGE_TIMEOUT)),
                $transform->getTransform()
            )->getUrl();

            $transform->setPresignedUrl($presignedUrl);

            $collection->add($transform);
        }
        $collection->indexBy('transform');

        return $collection;
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

        $presignedUrlService    = new PreSignedUrlService($config);

        $hashes                 = array_unique($collections->getPropertyValues(self::FIELD_HASH));
        $transforms             = $this->findByColumn(self::FIELD_HASH, $hashes)->groupBy(self::FIELD_HASH);
        $hydratedCollections    = \Schoolbox\Resource\Storage\Mapper::getInstance()->findByColumn(self::FIELD_HASH, $hashes);

        // Hydrate the transformations into the portrait with pre-signed URL
        /** @var \Schoolbox\Resource\Storage\Model $collection */
        foreach ($hydratedCollections as $collection) {
            /** @var Model $transform */
            foreach ($transforms[$collection->getHash()] as $transform) {
                $presignedUrl = $presignedUrlService->generate(
                    $collection,
                    Alaress_Date_Format::now()->addSeconds($config->get(ConfigMapper::NAME_BROWSER_CACHE_IMAGE_TIMEOUT)),
                    $transform->getTransform()
                )->getUrl();
                $transform->setPresignedUrl($presignedUrl);
                $transforms[$collection->getHash()]->indexBy('transform');
            }
        }

        return $transforms;
    }
}
