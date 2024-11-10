<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\BulkImport;

use PDOStatement;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Pastoral\Severity\Mapper as PastoralSeverityMapper;
use Schoolbox\Pastoral\SubType\Mapper as PastoralSubTypeMapper;
use Schoolbox\Pastoral\Tag;
use Schoolbox\Pastoral\Type\Mapper as PastoralTypeMapper;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\UserMapper;

/**
 * Mapper for pastoral bulk import records.
 *
 * @method Model createModel($idValue = null)
 */
class Mapper extends BaseMapper
{
    /**
     * The bulk import shares many fields with the base pastoral mapper.
     *
     * @return void
     */
    protected function mapping()
    {
        $this->hasTable('pastoral_bulk_import');
        $this->hasModel(Model::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('owner_id', UserMapper::class, 'id', null, null, 'owner', 'owner');
        $this->joins('zip_hash', StorageMapper::class, 'hash', null, null, 'zip', 'zip');
        $this->uses('file_path_template', self::TYPE_STRING);

        $this->joins('severity_id', PastoralSeverityMapper::class, 'id', null, null, 'severity', 'severity');
        $this->joins('type_id', PastoralTypeMapper::class, 'id', null, null, 'type', 'type');
        $this->joins('subtype_id', PastoralSubTypeMapper::class, 'id', null, null, 'subtype', 'subtype');

        $this->uses('heading', self::TYPE_STRING);
        $this->uses('body_public', self::TYPE_STRING);
        $this->uses('points', self::TYPE_DECIMAL);

        $this->uses('imported_at', self::TYPE_DATETIME);
        $this->uses('import_log', self::TYPE_JSON);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->joinsReverseMulti('pastoral_bulk_import_id', Access\Mapper::class, 'access', 'access');
        $this->joinsVia(
            'pastoral_bulk_import_tags',
            Tag\Mapper::class,
            'id',
            'tag_id',
            'pastoral_bulk_import_id',
            'id',
            null,
            null,
            'tags',
        );

        $this->registerCallback(self::CALLBACK_POSTSAVE, $this->savePastoralAccess(...));
    }

    /**
     * Include the owner's name in the select string.
     */
    protected function getSelectString()
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('owner', 'owner')}
        ";
    }

    /**
     * Include the owner's name in the join string.
     */
    protected function getFromString()
    {
        return "
            FROM {$this->getTable()}
            INNER JOIN {$this->getJoinSql('owner', '', 'owner', true)}
        ";
    }

    /**
     * Hydrate this item, with owner and all access and tags attached.
     *
     * @param PDOStatement $statement
     * @return GenericCollection<Model>
     */
    public function hydrateSelect(PDOStatement $statement)
    {
        $models = $this->createCollection();
        foreach ($statement as $row) {
            $models[] = $this->hydrate($row)
                ->setOwner($this->hydrateJoin($row, 'owner', 'owner'));
        }

        $ids = $models->getPropertyValues('id');
        if (!empty($ids)) {
            // Load access models, keyed by import ID
            /** @var Access\Mapper $accessMapper */
            $accessMapper = $this->getJoinMapper('access');
            $accessModels = $accessMapper
                ->findByColumn('pastoral_bulk_import_id', $ids)
                ->groupByCallback(fn (Access\Model $model) => $model->getPastoralBulkImport()->getId());

            // Load tag models, keyed by import ID
            $pb       = $this->getPredicateBuilder()->in('id', $ids);
            $tagQuery = $this->getDatabase()->prepareQuery("
                SELECT
                    {$this->getColumnString()},
                    {$this->getJoinColumnString('pastoral_bulk_import_tags', 'tag')}
                FROM {$this->getDatabase()->getQuoter()->quoteIdentifier($this->getTable())}
                INNER JOIN {$this->getJoinSql('pastoral_bulk_import_tags', '', 'tag')}
                WHERE {$pb->in('id', $ids)->getWhereIntersect()}
            ");
            $tagModels = [];
            $tagQuery->execute($pb->getParams());
            foreach ($tagQuery as $row) {
                $tagModels[$row['id']] ??= $this->getJoinMapper('pastoral_bulk_import_tags')->createCollection();
                $tagModels[$row['id']][] = $this->hydrateJoin($row, 'pastoral_bulk_import_tags', 'tag');
            }

            // Assign access and tags
            foreach ($models as $model) {
                $id = $model->getId();
                if (isset($accessModels[$id])) {
                    $model->setAccess($accessModels[$id]);
                }
                if (isset($tagModels[$id])) {
                    $model->setTags($tagModels[$id]);
                }
            }
        }
        return $models;
    }

    /**
     * Save access rows on the import object.
     *
     * Tags are auto-saved as part of the `joinsVia` query, and don't need to be
     * saved here.
     *
     * @param Model      $model
     * @param Model|null $previous
     * @return void
     * @throws BaseMapper\Exception
     */
    public function savePastoralAccess(Model $model, ?Model $previous = null): void
    {
        /** @var Access\Mapper $accessMapper */
        $accessMapper   = $this->getJoinMapper('access');
        $previousAccess = $previous?->getAccess();
        $currentAccess  = $model->getAccess();
        $removedAccess  = $previousAccess?->diffBy($currentAccess, 'id');
        if (!empty($removedAccess)) {
            $accessMapper->delete($removedAccess);
        }

        // Assign every access to the current model
        $accessMapper->save($currentAccess->map(fn (Access\Model $access) => $access->setPastoralBulkImport($model)));
    }
}
