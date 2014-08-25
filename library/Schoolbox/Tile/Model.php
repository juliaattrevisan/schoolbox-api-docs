<?php
/**
 * Model for tiles in Schoolbox.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 
namespace Schoolbox\Tile;

use Alaress_Date_Format;
use Schoolbox\Storage;
use Schoolbox\Tile\Layout;
use Schoolbox\ISoftDeletable;

class Model implements ISoftDeletable
{
    /**
     * Value of $titlePosition for when it's at the bottom.
     *
     * @var int
     */
    const TITLE_POSITION_BOTTOM = 2;

    /**
     * Value of $titlePosition for when it's at the top.
     *
     * @var int
     */
    const TITLE_POSITION_TOP = 1;

    /**
     * The primary key of this tile
     *
     * @var int
     */
    protected $id;

    /**
     * The layout this tile belongs to.
     *
     * @var Layout\Model
     */
    protected $tileLayout;

    /**
     * @var string
     */
    protected $titleText;

    /**
     * @var string
     */
    protected $titleColor;

    /**
     * @var string
     */
    protected $titleBackgroundColor;

    /**
     * @var int
     */
    protected $titlePosition;

    /**
     * @var string
     */
    protected $link;

    /**
     * @var Storage
     */
    protected $backgroundImage;

    /**
     * @param  string $backgroundColor
     * @return $this
     */
    public function setBackgroundColor($backgroundColor)
    {
        $this->backgroundColor = $backgroundColor;
        return $this;
    }

    /**
     * @return string
     */
    public function getBackgroundColor()
    {
        return $this->backgroundColor;
    }

    /**
     * @param  Storage $backgroundImage
     * @return $this
     */
    public function setBackgroundImage($backgroundImage)
    {
        $this->backgroundImage = $backgroundImage;
        return $this;
    }

    /**
     * @return Storage
     */
    public function getBackgroundImage()
    {
        return $this->backgroundImage;
    }

    /**
     * @param  Layout\Model $tileLayout
     * @return $this
     */
    public function setTileLayout($tileLayout)
    {
        $this->tileLayout = $tileLayout;
        return $this;
    }

    /**
     * @return Layout\Model
     */
    public function getTileLayout()
    {
        return $this->tileLayout;
    }

    /**
     * @param  string $link
     * @return $this
     */
    public function setLink($link)
    {
        $this->link = $link;
        return $this;
    }

    /**
     * @return string
     */
    public function getLink()
    {
        return $this->link;
    }

    /**
     * @param  string $titleBackgroundColor
     * @return $this
     */
    public function setTitleBackgroundColor($titleBackgroundColor)
    {
        $this->titleBackgroundColor = $titleBackgroundColor;
        return $this;
    }

    /**
     * @return string
     */
    public function getTitleBackgroundColor()
    {
        return $this->titleBackgroundColor;
    }

    /**
     * @param  string $titleColor
     * @return $this
     */
    public function setTitleColor($titleColor)
    {
        $this->titleColor = $titleColor;
        return $this;
    }

    /**
     * @return string
     */
    public function getTitleColor()
    {
        return $this->titleColor;
    }

    /**
     * @param  int $titlePosition
     * @return $this
     */
    public function setTitlePosition($titlePosition)
    {
        $this->titlePosition = $titlePosition;
        return $this;
    }

    /**
     * @return int
     */
    public function getTitlePosition()
    {
        return $this->titlePosition;
    }

    /**
     * @param  string $titleText
     * @return $this
     */
    public function setTitleText($titleText)
    {
        $this->titleText = $titleText;
        return $this;
    }

    /**
     * @return string
     */
    public function getTitleText()
    {
        return $this->titleText;
    }

    /**
     * @var string
     */
    protected $backgroundColor;

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
     * Is this object deleted?
     *
     * @return bool
     */
    public function isDeleted()
    {
        return $this->getDeletedAt() instanceof Alaress_Date_Format;
    }
}
