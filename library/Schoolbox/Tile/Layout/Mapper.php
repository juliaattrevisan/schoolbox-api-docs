<?php
/**
 * Mapper for tile layouts in Schoolbox.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Tile\Layout;

use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Tile\Model as TileModel;
use Schoolbox\Tile\Mapper as TileMapper;

class Mapper extends BaseMapper
{
    public function mapping()
    {
        $this->hasTable('tile_layout');
        $this->hasModel('Schoolbox\\Tile\\Layout\\Model');
        $this->hasMap(
            array(
                'column'     => 'id',
                'property'   => 'id',
                'type'       => self::TYPE_INTEGER,
                'identifier' => true,
            )
        );

        // Layout: will be a choice between grid and columns
        $this->hasMap(
            array(
                'column'   => 'layout_type',
                'property' => 'layoutType',
                'type'     => self::TYPE_INTEGER,
            )
        );

        // Number of columns when using column layout
        $this->hasMap(
            array(
                'column'   => 'column_count',
                'property' => 'columnCount',
                'type'     => self::TYPE_INTEGER,
            )
        );

        // Column padding: a % of the total width of the tile
        $this->uses('column_padding', self::TYPE_INTEGER);

        // Grid width: always px as far as i know
        $this->hasMap(
            array(
                'column'   => 'grid_width',
                'property' => 'gridWidth',
                'type'     => self::TYPE_INTEGER,
            )
        );

        // Grid padding: a number of pixels
        $this->uses('grid_padding', self::TYPE_INTEGER);

        // Aspect ratio for grid items
        $this->hasMap(
            array(
                'column'   => 'aspect_ratio_x',
                'property' => 'aspectRatioX',
                'type'     => self::TYPE_INTEGER,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'aspect_ratio_y',
                'property' => 'aspectRatioY',
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

        // Join to tiles as well
        $this->hasJoin(
            'tile',
            array(
                'mapper'    => 'Schoolbox\\Tile\\Mapper',
                'this_key'  => 'id',
                'other_key' => 'tile_layout_id',
                'multiple'  => 'true',
            )
        );
    }

    /**
     * Gets the tile layout, and fetches any tiles that are a part of it.
     *
     * @param  mixed $identifierValue
     * @return Model
     */
    public function findById($identifierValue)
    {
        /** @var Model $model */
        /** @var TileMapper $tileMapper */
        $model      = parent::findById($identifierValue);
        $tileMapper = $this->getJoinMapper('tile');
        $model->setTileList($tileMapper->findForLayout($model->getId()));
        return $model;
    }
}
