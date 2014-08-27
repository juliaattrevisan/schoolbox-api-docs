<?php
/**
 * Mapper for tile items in Schoolbox.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Tile;

use Schoolbox\Mapper as BaseMapper;

class Mapper extends BaseMapper
{
    public function mapping()
    {
        $this->hasTable('tile');
        $this->hasModel('Schoolbox\\Tile\\Model');
        $this->hasMap(
            array(
                'column'     => 'id',
                'property'   => 'id',
                'type'       => self::TYPE_INTEGER,
                'identifier' => true,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'tile_layout_id',
                'property' => 'tileLayout',
                'type'     => self::TYPE_MODEL,
                'join'     => 'tile_container',
                'join_def' => array(
                    'mapper'    => 'Schoolbox\\Tile\\Layout\\Mapper',
                    'other_key' => 'id',
                )
            )
        );

        // Title related fields
        $this->hasMap(
            array(
                'column'   => 'title_text',
                'property' => 'titleText',
                'type'     => self::TYPE_STRING,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'title_color',
                'property' => 'titleColor',
                'type'     => self::TYPE_STRING,
            )
        );
        // Hydrate as a color object?
        $this->hasMap(
            array(
                'column'   => 'title_background_color',
                'property' => 'titleBackgroundColor',
                'type'     => self::TYPE_STRING,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'title_position',
                'property' => 'titlePosition',
                'type'     => self::TYPE_INTEGER,
            )
        );

        // Link
        $this->hasMap(
            array(
                'column'   => 'link',
                'property' => 'link',
                'type'     => self::TYPE_STRING,
            )
        );

        // Background related fields
        $this->hasMap(
            array(
                'column'   => 'background_image_id',
                'property' => 'backgroundImage',
                'type'     => self::TYPE_MODEL,
                'join'     => 'backgroundImage',
                'join_def' => array(
                    'mapper'    => '\\Schoolbox\\StorageMapper',
                    'other_key' => 'storage_id',
                )
            )
        );
        $this->hasMap(
            array(
                'column'   => 'background_color',
                'property' => 'backgroundColor',
                'type'     => self::TYPE_STRING,
            )
        );

        // Order
        $this->hasMap(
            array(
                'column'   => 'order',
                'property' => 'order',
                'type'     => self::TYPE_INTEGER,
            )
        );

        // Created/Modified/Deleted
        $this->hasMap(
            array(
                'column'            => 'created_at',
                'property'          => 'createdAt',
                'type'              => self::TYPE_DATETIME,
                'created_timestamp' => true,
            )
        );
        $this->hasMap(
            array(
                'column'            => 'modified_at',
                'property'          => 'modifiedAt',
                'type'              => self::TYPE_DATETIME,
                'updated_timestamp' => true,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'deleted_at',
                'property' => 'deletedAt',
                'type'     => self::TYPE_DATETIME,
            )
        );
        $this->hasSoftDeleteDateField('deleted_at');
        $this->registerCallback('preSave', array($this, 'preSave'));
    }

    /**
     * Pre-save: ensures that an order is set for this tile if it doesn't have
     * one already.
     *
     * @param Model $object
     */
    public function preSave(Model $object)
    {
        $order = $object->getOrder();
        if (!isset($order)) {
            $statement = $this->getDatabase()->prepare(
                "SELECT COALESCE(MAX(`order`), -1)
                 FROM `{$this->getTable()}` WHERE `tile_layout_id` = :id"
            );
            $statement->bindValue('id', $object->getTileLayout()->getId(), \PDO::PARAM_INT);
            $statement->execute();
            $object->setOrder(intval($statement->fetchColumn())+1);
        }
    }
}
