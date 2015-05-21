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
                'type'     => self::TYPE_COLOR,
            )
        );
        // Hydrate as a color object?
        $this->hasMap(
            array(
                'column'   => 'title_background_color',
                'property' => 'titleBackgroundColor',
                'type'     => self::TYPE_COLOR,
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
                'column'   => 'background_image_hash',
                'property' => 'backgroundImage',
                'type'     => self::TYPE_MODEL,
                'join'     => 'backgroundImage',
                'join_def' => array(
                    'mapper'    => \Schoolbox\Resource\Storage\Mapper::class,
                    'other_key' => 'hash',
                )
            )
        );
        $this->hasMap(
            array(
                'column'   => 'background_image_contained',
                'property' => 'backgroundImageContained',
                'type'     => self::TYPE_BOOLEAN,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'background_color',
                'property' => 'backgroundColor',
                'type'     => self::TYPE_COLOR,
            )
        );
        $this->uses('background_position', self::TYPE_INTEGER);

        // Hover color
        $this->hasMap(
            array(
                'column'   => 'hover_color',
                'property' => 'hoverColor',
                'type'     => self::TYPE_COLOR,
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

    // When this item is fetched, in list or by ID: fetch all uploaded images
    // How do I do that?

    /**
     * Helper method for getting a query that retrieves a tile, and joins to
     * the background image view.
     *
     * @return string
     */
    protected function getBaseQuery()
    {
        return "
            SELECT
                {$this->getColumnString('t')},
                {$this->getJoinColumnString('backgroundImage', 'bi')}
            FROM `{$this->getTable()}` `t`
            LEFT JOIN {$this->getJoinSql('backgroundImage', 't', 'bi')}
        ";
    }

    /**
     * Gets the tile and its image.
     *
     * @param  int $identifierValue
     * @return Model
     * @throws BaseMapper\Exception if tile doesn't exist
     * @throws BaseMapper\Exception\Deleted if tile was soft deleted
     */
    public function findById($identifierValue)
    {
        $query = $this->getBaseQuery()
                . ' WHERE `t`.`deleted_at` IS NULL'
                . ' AND `t`.`id` = :id';
        $statement = $this->getDatabase()->prepare($query);
        $statement->execute(array('id' => $identifierValue));

        if ($row = $statement->fetch()) {
            /** @var Model $model */
            $model = $this->hydrate($row, 't');
            $model->setBackgroundImage($this->hydrateJoin($row, 'backgroundImage', 'bi'));
            $this->throwIfDeleted($model);
            return $model;
        }
        throw new BaseMapper\Exception('Instance of object not found');
    }

    /**
     * Gets all tiles for this layout ID, as well as all of its images.
     *
     * @param  int $layoutId
     * @return Model[]|\Schoolbox\Collection
     */
    public function findForLayout($layoutId)
    {
        $query = $this->getBaseQuery()
                . ' WHERE `t`.`deleted_at` IS NULL'
                . ' AND `t`.`tile_layout_id` = :layoutId'
                . ' ORDER BY `t`.`order` ASC';
        $statement = $this->getDatabase()->prepare($query);
        $statement->execute(array('layoutId' => $layoutId));
        return $this->hydrateList(
            $statement,
            't',
            function ($mapper, $model, $row) {
                $model->setBackgroundImage($mapper->hydrateJoin($row, 'backgroundImage', 'bi'));
                return $model;
            }
        );
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
