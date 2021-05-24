<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Calendar\Relation;

use Schoolbox;
use Schoolbox\Mapper;

/**
 * Class CategoryMapper
 *
 * Maintains relationships between Folders and Events via event_folder
 * These are a Group level's events
 *
 * @package Schoolbox\Calendar\Relation
 */
class CategoryMapper extends Schoolbox\Mapper
{

    /**
     * Mapping
     *
     * @return void
     * @throws Mapper\DefinitionException
     */
    protected function mapping()
    {
        $this->hasTable('event_folder');
        $this->hasModel(Category::class);

        $this->hasMap(
            array(
                'column' => 'event_id',
                'property' => 'eventId',
                'type' => self::TYPE_INTEGER,
            )
        );
        $this->hasMap(
            array(
                'column' => 'folder_id',
                'property' => 'categoryId',
                'type' => self::TYPE_INTEGER,
            )
        );
        $this->hasJoin(
            'category',
            array(
                'mapper' => '\\Schoolbox\\Resource\\Folder\\Mapper',
                'this_key' => 'folder_id',
                'other_key' => 'id',
            )
        );
    }


    /**
     * This mapper doesn't raise notifications - the event mapper handles that.
     *
     * @param object $object
     * @param string $verb
     * @param null   $previous
     *
     * @return true
     */
    protected function raiseNotification($object, $verb, $previous = null)
    {
        return true;
    }
}
