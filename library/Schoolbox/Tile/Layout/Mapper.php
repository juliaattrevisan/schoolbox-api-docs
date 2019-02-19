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
use Schoolbox\Tile\Mapper as TileMapper;

/**
 * Mapper for tile layouts.
 */
class Mapper extends BaseMapper
{
    public function mapping()
    {
        $this->hasTable('tile_layout');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);

        // Layout: will be a choice between grid and columns
        $this->uses(
            'layout_type',
            self::TYPE_INTEGER,
            self::COLUMN_ENUM,
            'layoutType',
            [Model::LAYOUT_TYPE_GRID, Model::LAYOUT_TYPE_COLUMN]
        );

        // Number of columns when using column layout
        $this->uses('column_count', self::TYPE_INTEGER);

        // Column padding: a % of the total width of the tile
        $this->uses('column_padding', self::TYPE_INTEGER);

        // Grid width: always px as far as i know
        $this->uses('grid_width', self::TYPE_INTEGER);

        // Grid padding: a number of pixels
        $this->uses('grid_padding', self::TYPE_INTEGER);

        // Aspect ratio for grid items
        $this->uses('aspect_ratio_x', self::TYPE_INTEGER);
        $this->uses('aspect_ratio_y', self::TYPE_INTEGER);

        // Created/Modified/Deleted
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('modified_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        // Join to tiles as well
        $this->joinsReverseMulti('tile_layout_id', TileMapper::class, 'tileList', 'tile');
//        $this->hasJoin(
//            'tile',
//            [
//                'mapper'    => TileMapper::class,
//                'this_key'  => 'id',
//                'other_key' => 'tile_layout_id',
//                'multiple'  => 'true',
//            ]
//        );


        // join through to folder, role, homepage template
    }

    /**
     * Gets the tile layout, and fetches any tiles that are a part of it.
     *
     * @param  mixed $identifierValue
     * @return Model
     * @throws BaseMapper\Exception
     * @throws BaseMapper\Exception\Deleted
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
