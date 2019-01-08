<?php
/**
 * TextboxMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Resource\Textbox;

use Schoolbox\Comms\News\Audience\ArticleResolver;
use Schoolbox\Resource\Textbox\Search\Result;
use Schoolbox\User;
use Schoolbox\Mapper\Searchable;
use Schoolbox\Collection;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;

/**
 * @method Model createModel($idValue = null)
 */
class Mapper extends \Schoolbox\Mapper implements Searchable
{
    /**
     * Mapping
     *
     * @return Bool|void
     */
    protected function mapping()
    {
        $this->hasTable('textBox');
        $this->hasModel(Model::class);
        $this->uses('textboxID', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER, 'id');
        $this->uses('heading', self::TYPE_STRING);
        $this->uses('body', self::TYPE_STRING);
        $this->uses('deleted', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE, 'deletedAt');
        $this->joins('categoryID', FolderMapper::class, 'id', null, null, 'category', 'category');
    }

    /**
     * @return string
     */
    protected function getSelectString()
    {
        return "
            SELECT {$this->getColumnString()}, 
                   {$this->getJoinColumnString('category', 'f')}
            FROM `" . $this->getTable() . "`
            JOIN {$this->getJoinSql('category', '', 'f')}
        ";
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
     * Performs a search with the given parameters, and returns a Collection of the resultant set.
     *
     * This also returns a count of all the items found in the search, not limited
     * by params
     *
     * @param ArticleResolver $articleResolver
     * @param User            $user
     * @param string          $keyword
     * @param int             $offset
     * @param int             $limit
     * @param string          $sortProperty
     * @param string          $sortDirection
     * @return Result
     */
    public function search(ArticleResolver $articleResolver, $user, $keyword, $offset = 0, $limit = self::DEFAULT_LIMIT, $sortProperty = '', $sortDirection = 'DESC')
    {
        if ($limit == 0) {
            $limit = self::DEFAULT_LIMIT;
        }

        $searcher = new Result();
        $searcher->search($articleResolver, $user, $this, $keyword, $sortProperty, $sortDirection, $offset, $limit);

        return $searcher;
    }
}
