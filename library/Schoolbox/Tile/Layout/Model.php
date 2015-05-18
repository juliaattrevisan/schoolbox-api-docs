<?php
/**
 * Model for tile layouts in Schoolbox.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 
namespace Schoolbox\Tile\Layout;

use Alaress_Date_Format;
use Schoolbox\Collection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Tile\Model as TileModel;

class Model implements ISoftDeletable, \JsonSerializable
{
    /**
     * Value of $layoutType for grid layouts.
     *
     * @var int
     */
    const LAYOUT_TYPE_GRID = 1;

    /**
     * Value of $layoutType for column layouts.
     *
     * @var int
     */
    const LAYOUT_TYPE_COLUMN = 2;

    /**
     * The primary key of this grid.
     *
     * @var int
     */
    protected $id;

    /**
     * The list of tiles that belong to this layout.
     *
     * @var Collection|TileModel[]
     */
    protected $tileList;

    /**
     * The layout type of this grid, either 'grid' or 'column'
     *
     * @var int
     */
    protected $layoutType = self::LAYOUT_TYPE_COLUMN;

    /**
     * The number of columns in this layout (if using column layout)
     *
     * @var int
     */
    protected $columnCount = 3;

    /**
     * The padding for columns, as a % of the width of a column.
     *
     * @var float
     */
    protected $columnPadding = 0.0;

    /**
     * The number of pixels wide each tile in layout is (if using grid layout)
     *
     * @var int
     */
    protected $gridWidth = 100;

    /**
     * The number of pixels padding for grid layout
     *
     * @var int
     */
    protected $gridPadding = 0;

    /**
     * The width ratio of the items within this grid.
     *
     * @var int
     */
    protected $aspectRatioX = 16;

    /**
     * The height ratio of the items within this grid.
     *
     * @var int
     */
    protected $aspectRatioY = 9;

    /**
     * @var Alaress_Date_Format
     */
    protected $createdAt;

    /**
     * @var Alaress_Date_Format
     */
    protected $modifiedAt;

    /**
     * @var Alaress_Date_Format
     */
    protected $deletedAt;

    /**
     * Constructor: creates this layout with an empty tile list
     * (which can be overridden with setTileList())
     */
    public function __construct()
    {
        $this->setTileList(new Collection());
    }

    /**
     * @param  int $aspectRatioX
     * @return $this
     */
    public function setAspectRatioX($aspectRatioX)
    {
        $this->aspectRatioX = $aspectRatioX;
        return $this;
    }

    /**
     * @return int
     */
    public function getAspectRatioX()
    {
        return $this->aspectRatioX;
    }

    /**
     * @param  int $aspectRatioY
     * @return $this
     */
    public function setAspectRatioY($aspectRatioY)
    {
        $this->aspectRatioY = $aspectRatioY;
        return $this;
    }

    /**
     * @return int
     */
    public function getAspectRatioY()
    {
        return $this->aspectRatioY;
    }

    /**
     * @param  int $columnCount
     * @return $this
     */
    public function setColumnCount($columnCount)
    {
        $this->columnCount = $columnCount;
        return $this;
    }

    /**
     * @return int
     */
    public function getColumnCount()
    {
        return $this->columnCount;
    }

    /**
     * @param  Alaress_Date_Format $createdAt
     * @return $this
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @param  Alaress_Date_Format $deletedAt
     * @return $this
     */
    public function setDeletedAt($deletedAt)
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getDeletedAt()
    {
        return $this->deletedAt;
    }

    /**
     * @param  int $gridWidth
     * @return $this
     */
    public function setGridWidth($gridWidth)
    {
        $this->gridWidth = $gridWidth;
        return $this;
    }

    /**
     * @return int
     */
    public function getGridWidth()
    {
        return $this->gridWidth;
    }

    /**
     * @param  int $id
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param  int $layoutType
     * @return $this
     */
    public function setLayoutType($layoutType)
    {
        $this->layoutType = $layoutType;
        return $this;
    }

    /**
     * @return int
     */
    public function getLayoutType()
    {
        return $this->layoutType;
    }

    /**
     * @param  Alaress_Date_Format $modifiedAt
     * @return $this
     */
    public function setModifiedAt($modifiedAt)
    {
        $this->modifiedAt = $modifiedAt;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getModifiedAt()
    {
        return $this->modifiedAt;
    }

    /**
     * @param  Collection|TileModel[] $tileList
     * @return $this
     */
    public function setTileList($tileList)
    {
        $this->tileList = $tileList;
        return $this;
    }

    /**
     * @return Collection|TileModel[]
     */
    public function getTileList()
    {
        return $this->tileList;
    }

    /**
     * @param  TileModel $tile
     * @return $this
     */
    public function addTile(TileModel $tile)
    {
        $this->getTileList()->add($tile);
        return $this;
    }

    /**
     * Is this a grid layout?
     *
     * @return bool
     */
    public function isGridLayoutType()
    {
        return $this->getLayoutType() == self::LAYOUT_TYPE_GRID;
    }

    /**
     * Is this a column layout?
     *
     * @return bool
     */
    public function isColumnLayoutType()
    {
        return $this->getLayoutType() == self::LAYOUT_TYPE_COLUMN;
    }

    /**
     * Gets the aspect ratio of tiles in this layout
     *
     * @return string
     */
    public function getAspectRatio()
    {
        return sprintf('%d:%d', $this->getAspectRatioX(), $this->getAspectRatioY());
    }

    /**
     * Sets the aspect ratio of tiles in this layout
     *
     * @param  string $aspectRatio A string in format "x:y"
     * @return $this
     * @throws \InvalidArgumentException if $aspectRatio isn't in format "x:y"
     */
    public function setAspectRatio($aspectRatio)
    {
        if (!preg_match('/^(?P<x>\\d+):(?P<y>\\d+)$/', $aspectRatio, $matches)) {
            throw new \InvalidArgumentException('Aspect ratio must be in format "x:y"');
        }
        $this->setAspectRatioX($matches['x'])->setAspectRatioY($matches['y']);
        return $this;
    }

    /**
     * Does this layout have tiles in a landscape shape (aspect ratio 16:9)?
     *
     * @return bool
     */
    public function isLandscapeShape()
    {
        return $this->getAspectRatio() === '16:9';
    }

    /**
     * Does this layout have tiles in a portrait shape (aspect ratio 9:16)?
     *
     * @return bool
     */
    public function isPortraitShape()
    {
        return $this->getAspectRatio() === '9:16';
    }

    /**
     * Does this layout have tiles in a square shape (aspect ratio 1:1)?
     *
     * @return bool
     */
    public function isSquareShape()
    {
        return $this->getAspectRatio() === '1:1';
    }

    /**
     * Does this layout have tiles in a panoramic shape (aspect ratio 16:5)?
     *
     * @return bool
     */
    public function isPanoramicShape()
    {
        return $this->getAspectRatio() === '16:5';
    }

    /**
     * Is this a custom shape (none of portrait, square, landscape, panorama?)
     *
     * @return bool
     */
    public function isCustomShape()
    {
        return !(
            $this->isPortraitShape()
            || $this->isLandscapeShape()
            || $this->isSquareShape()
            || $this->isPanoramicShape()
        );
    }

    /**
     * Is this object deleted?
     *
     * @return bool
     */
    public function isDeleted()
    {
        return $this->getDeletedAt() instanceof Alaress_Date_Format && !$this->getDeletedAt()->isNull();
    }

    /**
     * Specify data which should be serialized to JSON
     *
     * @return mixed
     */
    public function jsonSerialize()
    {
        return array(
            'id'       => $this->getId(),
            'tileList' => $this->getTileList(),
        );
    }
}
