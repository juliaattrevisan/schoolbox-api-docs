<?php
/**
 * Model for grid layouts in Schoolbox.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 
namespace Schoolbox\Grid;

use Alaress_Date_Format;
use Schoolbox\ISoftDeletable;

class Model implements ISoftDeletable
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
     * The layout type of this grid, either 'grid' or 'column'
     *
     * @var int
     */
    protected $layoutType;

    /**
     * The number of columns in this layout (if using column layout)
     *
     * @var int
     */
    protected $columnCount;

    /**
     * The number of pixels wide this layout is (if using grid layout)
     *
     * @var int
     */
    protected $gridWidth;

    /**
     * The width ratio of the items within this grid.
     *
     * @var int
     */
    protected $aspectRatioX;

    /**
     * The height ratio of the items within this grid.
     *
     * @var int
     */
    protected $aspectRatioY;

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
     * Gets the aspect ratio of grid items in this grid
     *
     * @return string
     */
    public function getAspectRatio()
    {
        return sprintf('%d:%d', $this->getAspectRatioX(), $this->getAspectRatioY());
    }

    /**
     * Is this a landscape layout (16:9)?
     *
     * @return bool
     */
    public function isLandscape()
    {
        return $this->getAspectRatio() === '16:9';
    }

    /**
     * Is this a portrait layout (9:16)?
     *
     * @return bool
     */
    public function isPortrait()
    {
        return $this->getAspectRatio() === '9:16';
    }

    /**
     * Is this a square layout (1:1)?
     *
     * @return bool
     */
    public function isSquare()
    {
        return $this->getAspectRatio() === '1:1';
    }

    /**
     * Is this a panoramic layout (16:5)?
     *
     * @return bool
     */
    public function isPanoramic()
    {
        return $this->getAspectRatio() === '16:5';
    }

    /**
     * Is this a custom layout (none of portrait, square, landscape, panorama?)
     *
     * @return bool
     */
    public function isCustomLayout()
    {
        return !(
            $this->isPortrait() || $this->isLandscape() || $this->isSquare() || $this->isPanoramic()
        );
    }

    /**
     * Is this object deleted?
     *
     * @return bool
     */
    public function isDeleted()
    {
        return $this->getDeletedAt() instanceof Alaress_Date_Format;
    }
}
