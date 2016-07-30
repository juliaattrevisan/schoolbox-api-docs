<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Folder\Code;


use Schoolbox\Collection;

class Mapper extends \Schoolbox\Mapper
{

    protected function mapping()
    {
        $this->hasModel('\\Schoolbox\\Resource\\Folder\\Code\\Model');
        $this->hasTable('folder_code');
        $this->hasMap(
            array(
                'column'     => 'folder_id',
                'property'   => 'folder',
                'type'       => self::TYPE_MODEL,
                'join'       => 'folder',
                'identifier' => true,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'code',
                'property' => 'code',
                'type'     => self::TYPE_STRING,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'deleted_at',
                'property' => 'deletedAt',
                'type'     => self::TYPE_DATETIME,
            )
        );
        $this->hasJoin(
            'folder', array(
                'mapper'    => '\\Schoolbox\\Resource\\Folder\\Mapper',
                'this_key'  => 'folder_id',
                'other_key' => 'id',
            )
        );

        $this->hasSoftDeleteDateField('deleted_at');
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
        //
        // no IDs? return early so no invalid query is called.
        if (empty($category_ids)) {
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
     * Helper method for adding codes to all categories within this collection.
     *
     * @todo Consider making this method generic to populate any collection
     *
     * @param Collection $collection A list of categories, or objects
     *                                      containing categories.
     * @param callable $retrieve_function A callback for retrieving category
     *                                      from each $category_collection.
     *                                      If absent, assumes each member is a
     *                                      Category object.
     *
     * @return void
     */
    public function applyCodesToCategories($collection, $retrieve_function = null)
    {
        $cat_id_list = array();

        if (!is_callable($retrieve_function)) {
            $retrieve_function = function ($item) {
                return $item;
            };
        }

        //
        // Get each folder ID, and then get each code from the folder.
        foreach ($collection as $item) {
            $cat_id_list[] = $retrieve_function($item)->getId();
        }
        $grouped_codes = array();
        foreach ($this->findForCategories(array_unique($cat_id_list)) as $code) {
            $grouped_codes[$code->getFolder()->getId()][] = $code;
        }

        //
        // Add codes to each folder
        foreach ($collection as $item) {
            $folder = $retrieve_function($item);
            $cat_id   = $folder->getId();
            if (!empty($grouped_codes[$cat_id])) {
                foreach ($grouped_codes[$cat_id] as $code) {
                    $folder->addCode($code);
                }
            }
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