<?php
/**
 * TextboxMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Textbox;

use Schoolbox\Textbox\Model;
use Schoolbox\Collection;
use \Schoolbox\Resource\Folder\Mapper as FolderMapper;

class Mapper extends \Schoolbox\Mapper
{

    /**
     * Mapping
     *
     * @return Bool|void
     */
    protected function mapping()
    {
        $this->hasTable('textBox');
        $this->hasModel('\\Schoolbox\\Textbox\\Model');

        $this->hasMap(
            array(
                'column'     => 'textBoxID',
                'property'   => 'id',
                'type'       => self::TYPE_INTEGER,
                'identifier' => true,
            )
        );

        $this->hasMap(
            array(
                'column'   => 'categoryID',
                'property' => 'category',
                'type'     => self::TYPE_MODEL,
                'join'     => 'category'
            )
        );

        $this->hasMap(
            array(
                'column'   => 'heading',
                'property' => 'heading',
                'type'     => self::TYPE_STRING,
            )
        );

        $this->hasMap(
            array(
                'column'   => 'body',
                'property' => 'body',
                'type'     => self::TYPE_STRING,
            )
        );

        $this->hasMap(
            array(
                'column'   => 'deleted',
                'property' => 'deletedAt',
                'type'     => self::TYPE_DATETIME,
            )
        );

        $this->hasJoin(
            'category', array(
                'multiple'  => false,
                'mapper'    => '\\Schoolbox\\Resource\\Folder\\Mapper',
                'this_key'  => 'categoryID',
                'other_key' => 'id',
            )
        );

        $this->hasSoftDeleteDateField('deleted');
    }

    /**
     * Search the user table for particular user(s)
     *
     * @param Model $textbox
     * @return mixed
     */
    public function findComponentUsage(Model $textbox)
    {
        $instanceMapper = new \Component_InstanceMapper();

        $query = "
            SELECT *
            FROM component_definition
                INNER JOIN component_config_definition ON component_definition.id = component_config_definition.component_definition_id
                INNER JOIN component_config_instance ON component_config_instance.component_config_definition_id = component_config_definition.id
                INNER JOIN component_instance ON component_config_instance.component_instance_id = component_instance.id
                WHERE class = 'Component_Homepage_TextBoxController'
            AND `key` = 'textbox'
            AND `identifier` = :categoryId
            AND `value` = :textboxId
		";

        $statement = $this->database->prepare($query);
        $statement->bindParam(':categoryId', $textbox->getCategory()->getId());
        $statement->bindParam(':textboxId', $textbox->getId());
        $statement->execute();

        /* Manually construct hierarchy of blog items and their comments
         */
        $collection = new Collection();

        foreach ($statement as $row) {
            $componentInstance = $instanceMapper->hydrate($row);

            $collection->add($componentInstance);
        }

        return $collection;
    }

    /**
     * Search
     *
     * @param $keyword
     * @param int $offset
     * @param int $limit
     * @param null $count
     * @param string $sort_by
     * @param string $sort_dir
     * @return \Schoolbox\Collection
     */
    public function search($keyword, $offset = 0, $limit = 20, &$count = null, $sort_by = 'cname', $sort_dir = 'ASC')
    {
        $categoryMapper = new FolderMapper();

        $offset     = (int)$offset;
        $limit      = (int)$limit;
        $sortBy     = 'name';
        $sortDir    = strtoupper($sort_dir) == 'ASC' ? 'ASC' : 'DESC';
        $searchTerm = "%{$keyword}%";

        /* Search for matching in mapper's columns
         */
        $found = false;

        foreach ($this->map as $m) {
            if ($m['column'] == $sort_by) {
                $sortBy = "t.{$sort_by}";
                $found  = true;
                break;
            }
        }

        /* Search for matching in join's columns
         */
        if (!$found) {
            foreach ($categoryMapper->map as $m) {
                if ($m['column'] == $sort_by) {
                    $sortBy = "c.{$sort_by}";
                    break;
                }
            }
        }

        $query = "
             SELECT
            	{$this->getColumnString('t')},
            	{$categoryMapper->getColumnString('c')}
            FROM `{$this->table}` t
            LEFT JOIN {$this->getJoinSql('category', 't', 'c')}
            WHERE t.heading LIKE :like
            AND t.deleted IS NULL
            AND c.deleted_at IS NULL
            ORDER BY {$sortBy} {$sortDir}
            LIMIT {$offset}, {$limit}
        ";

        $statement = $this->database->prepare($query);
        $statement->bindParam(':like', $searchTerm);
        $statement->execute();

        $collection = new Collection();

        foreach ($statement as $row) {
            $textbox  = $this->hydrate($row, 't');
            $category = $categoryMapper->hydrate($row, 'c');

            $textbox->setCategory($category);

            $collection->add($textbox);
        }

        return $collection;
    }
}