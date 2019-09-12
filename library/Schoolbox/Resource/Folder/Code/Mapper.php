<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Folder\Code;

use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use function array_filter;
use function array_unique;

/**
 * @method Model createModel($idValue = null)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method GenericCollection|Model[] createCollection($contents = [])
 */
class Mapper extends \Schoolbox\Mapper
{

    protected function mapping()
    {
        $this->hasModel(Model::class);
        $this->hasTable('folder_code');

        $this->uses('code', self::TYPE_STRING);
        $this->joins('folder_id', FolderMapper::class, 'id', null, null, 'folder', 'folder');
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }

    /**
     * Retrieves category codes for a given category.
     *
     * @param int $category_id
     *
     * @return \Schoolbox\Collection|Model[]
     */
    public function findForCategory($category_id)
    {
        return $this->findForCategories(array($category_id));
    }

    /**
     * Retrieves category codes for a given set of categories.
     *
     * @param array $folder_ids
     *
     * @return \Schoolbox\Collection|Model[]
     */
    public function findForCategories($folder_ids)
    {
        // no IDs? return early so no invalid query is called.
        if (empty($folder_ids)) {
            return $this->hydrateList(array());
        }
        $params    = $this->getArrayParameters($folder_ids, 'id');
        $statement = $this->database->prepare(
            "
            SELECT {$this->getColumnString()}
            FROM {$this->table}
            WHERE folder_id IN {$this->getArrayPlaceholders($folder_ids, 'id')}
            AND deleted_at IS NULL
            ORDER BY code ASC, folder_id ASC
        "
        );
        $statement->execute($params);
        return $this->hydrateList($statement);
    }

    /**
     * Helper method for adding codes to all folders within this collection.
     *
     * @param Collection    $collection       A list of folders, or objects containing folders.
     * @param callable|null $retrieveFunction A callback for retrieving folder from each collection item.
     *                                        If absent, assumes each member is a Folder object.
     * @return void
     * @throws \UnexpectedValueException
     */
    public function applyCodesToCategories(Collection $collection, callable $retrieveFunction = null): void
    {
        // Retrieve folder set
        $retrieveFunction = $retrieveFunction ?? static function ($item): Folder\Model {
                return $item;
            };

        /** @var GenericCollection|Folder\Model[] $folders */
        $folders = new GenericCollection(Folder\Model::class);
        foreach ($collection as $item) {
            $folders->add($retrieveFunction($item));
        }

        // Fetch folderCodes from DB and group by folder.id
        $folderCodes            = $this->findForCategories(array_unique(array_filter($folders->getPropertyValues('id'))));
        $folderCodesPerFolderId = $folderCodes->groupByCallback(static function (Model $folderCode) {
            return $folderCode->getFolder()->getId();
        });

        // Apply to folders
        foreach ($folders as $folder) {
            $folder->setCodes($folderCodesPerFolderId->getOffset($folder->getId()) ?? $this->createCollection());
        }
    }


    /**
     * This model does not have any notifications
     *
     * @param object $object
     * @param string $verb
     * @param null $previous
     * @return bool
     */
    protected function raiseNotification($object, $verb, $previous = null)
    {
        // noop
        return false;
    }

}
