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
use Alaress\Color\Color;
use Schoolbox\Resource\Storage\IStorage;
use Schoolbox\Tile\Layout;
use Schoolbox\ISoftDeletable;

class Model implements ISoftDeletable, \JsonSerializable
{
    /**
     * Value of $titlePosition for when it's at the top left.
     *
     * @var int
     */
    const TITLE_POSITION_TOPLEFT = 7;

    /**
     * Value of $titlePosition for when it's at the top.
     *
     * @var int
     */
    const TITLE_POSITION_TOP = 8;

    /**
     * Value of $titlePosition for when it's at the top right.
     *
     * @var int
     */
    const TITLE_POSITION_TOPRIGHT = 9;

    /**
     * Value of $titlePosition for when it's at the left.
     *
     * @var int
     */
    const TITLE_POSITION_LEFT = 4;

    /**
     * Value of $titlePosition for when it's at the center.
     *
     * @var int
     */
    const TITLE_POSITION_CENTER = 5;

    /**
     * Value of $titlePosition for when it's at the right.
     *
     * @var int
     */
    const TITLE_POSITION_RIGHT = 6;

    /**
     * Value of $titlePosition for when it's at the bottom left.
     *
     * @var int
     */
    const TITLE_POSITION_BOTTOMLEFT = 1;

    /**
     * Value of $titlePosition for when it's at the bottom.
     *
     * @var int
     */
    const TITLE_POSITION_BOTTOM = 2;

    /**
     * Value of $titlePosition for when it's at the bottom right.
     *
     * @var int
     */
    const TITLE_POSITION_BOTTOMRIGHT = 3;

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
     * @var Color
     */
    protected $titleColor;

    /**
     * @var Color
     */
    protected $titleBackgroundColor;

    /**
     * @var int
     */
    protected $titlePosition = self::TITLE_POSITION_TOP;

    /**
     * @var string
     */
    protected $link;

    /**
     * @var IStorage
     */
    protected $backgroundImage;

    /**
     * Is the entire image contained within the tile (background-size: contain)?
     *
     * If not, the image will expand to fit the tile (background-size: cover).
     *
     * @var bool
     */
    protected $backgroundImageContained;

    /**
     * @var int
     */
    protected $order;

    /**
     * @var Color
     */
    protected $backgroundColor;

    /**
     * @var Color
     */
    protected $hoverColor;

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
     * Creates this object with sensible default colors.
     * * Tile background: transparent
     * * Title text: black
     * * Title background: transparent
     * * Hover background: transparent
     */
    public function __construct()
    {
        $this
            ->setBackgroundColor(new Color(0, 0, 0, 0))
            ->setTitleBackgroundColor(new Color(0, 0, 0, 0))
            ->setTitleColor(new Color(0, 0, 0, 1))
            ->setHoverColor(new Color(0, 0, 0, 0))
        ;
    }

    /**
     * @param  Color $backgroundColor
     * @return $this
     */
    public function setBackgroundColor($backgroundColor)
    {
        $this->backgroundColor = $backgroundColor;
        return $this;
    }

    /**
     * @return Color
     */
    public function getBackgroundColor()
    {
        return $this->backgroundColor;
    }

    /**
     * @param  Color $hoverColor
     * @return $this
     */
    public function setHoverColor($hoverColor)
    {
        $this->hoverColor = $hoverColor;
        return $this;
    }

    /**
     * @return Color
     */
    public function getHoverColor()
    {
        return $this->hoverColor;
    }

    /**
     * @param  IStorage $backgroundImage
     * @return $this
     */
    public function setBackgroundImage($backgroundImage)
    {
        $this->backgroundImage = $backgroundImage;
        return $this;
    }

    /**
     * @return IStorage
     */
    public function getBackgroundImage()
    {
        return $this->backgroundImage;
    }

    /**
     * @return boolean
     */
    public function getBackgroundImageContained()
    {
        return $this->backgroundImageContained;
    }

    /**
     * @param  boolean $backgroundImageContained
     *
     * @return $this
     */
    public function setBackgroundImageContained($backgroundImageContained)
    {
        $this->backgroundImageContained = $backgroundImageContained;
        return $this;
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
     * @param  Color $titleBackgroundColor
     * @return $this
     */
    public function setTitleBackgroundColor($titleBackgroundColor)
    {
        $this->titleBackgroundColor = $titleBackgroundColor;
        return $this;
    }

    /**
     * @return Color
     */
    public function getTitleBackgroundColor()
    {
        return $this->titleBackgroundColor;
    }

    /**
     * @param  Color $titleColor
     * @return $this
     */
    public function setTitleColor($titleColor)
    {
        $this->titleColor = $titleColor;
        return $this;
    }

    /**
     * @return Color
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
     * @param  int $order
     * @return $this
     */
    public function setOrder($order)
    {
        $this->order = $order;
        return $this;
    }

    /**
     * @return int
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * @return bool
     */
    public function isTitlePositionTop()
    {
        return $this->getTitlePosition() == self::TITLE_POSITION_TOP;
    }

    /**
     * @return bool
     */
    public function isTitlePositionBottom()
    {
        return $this->getTitlePosition() == self::TITLE_POSITION_BOTTOM;
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
            'id'        => $this->getId(),
            'titleText' => $this->getTitleText(),
        );
    }
}
