<?php
/**
 * Mapper for tile items in Schoolbox.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Tile;

use PDO;
use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\File\Mimetype;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\Resource\Storage\Model as StorageModel;

class Mapper extends BaseMapper
{
    public function mapping()
    {
        $this->hasTable('tile');
        $this->hasModel(Model::class);
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
                    'mapper'    => Layout\Mapper::class,
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
                'column'   => 'title_text_min_size',
                'property' => 'titleTextMinSize',
                'type'     => self::TYPE_INTEGER,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'title_text_max_size',
                'property' => 'titleTextMaxSize',
                'type'     => self::TYPE_INTEGER,
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
        $this->hasMap(
            array(
                'column'   => 'link_target',
                'property' => 'linkTarget',
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
                    'mapper'    => StorageMapper::class,
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


    /**
     * Always join onto image and its mime type
     *
     * @return string
     */
    protected function getSelectString()
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('backgroundImage', 'bi')},
                {$this->getJoinColumnString('backgroundImage.allowedFileExtension', 'bim')}
        ";
    }


    /**
     * Always join onto image and its mime type
     *
     * @return string
     */
    protected function getFromString()
    {
        $table = $this->database->quoteIdentifier($this->table);
        return "
            FROM $table
            LEFT JOIN {$this->getJoinSql('backgroundImage', '', 'bi', true)}
            LEFT JOIN {$this->getJoinSql('backgroundImage.allowedFileExtension', 'bi', 'bim', true)}
        ";
    }


    /**
     * @param PDOStatement $statement
     *
     * @return Collection
     * @throws BaseMapper\Exception
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $c = $this->createCollection();

        while ($r = $statement->fetch(PDO::FETCH_ASSOC)) {
            /** @var Model $item */
            $item = $this->hydrate($r);

            /** @var StorageModel $backgroundImage */
            $backgroundImage = $this->hydrateJoin($r, 'backgroundImage', 'bi');
            if ($backgroundImage) {
                /** @var Mimetype $mimeType */
                $mimeType = $this->hydrateJoin($r, 'backgroundImage.allowedFileExtension', 'bim');
                if ($mimeType) {
                    $backgroundImage->setMimetype($mimeType);
                }

                $item->setBackgroundImage($backgroundImage);
            }

            $c->add($item);
        }

        return $c;
    }


    /**
     * Gets all tiles for this layout ID, as well as all of its images.
     *
     * @param  int $layoutId
     * @return Model[]|\Schoolbox\Collection
     */
    public function findForLayout($layoutId)
    {
        return $this->findByColumn('tile_layout_id', $layoutId);
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
