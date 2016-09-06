<?php
/**
 * TextboxMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Textbox;

use Schoolbox\Resource\Textbox\Search\Result;
use Schoolbox\User;
use Schoolbox\Mapper\Searchable;
use Schoolbox\Collection;

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
     * Performs a search with the given parameters, and returns a Collection of the resultant set.
     *
     * This also returns a count of all the items found in the search, not limited
     * by params
     *
     * @param User $user
     * @param string $keyword
     * @param int $offset
     * @param int $limit
     * @param string $sortProperty
     * @param string $sortDirection
     * @return Collection
     */
    public function search($user, $keyword, $offset = 0, $limit = self::DEFAULT_LIMIT, $sortProperty = '', $sortDirection = 'DESC')
    {
        if ($limit == 0) {
            $limit = self::DEFAULT_LIMIT;
        }

        $searcher = new Result();
        $searcher->search($user, $this, $keyword, $sortProperty, $sortDirection, $offset, $limit);

        return $searcher;
    }
}
