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
    const POSITION_TOPLEFT = 7;

    /**
     * Value of $titlePosition for when it's at the top.
     *
     * @var int
     */
    const POSITION_TOP = 8;

    /**
     * Value of $titlePosition for when it's at the top right.
     *
     * @var int
     */
    const POSITION_TOPRIGHT = 9;

    /**
     * Value of $titlePosition for when it's at the left.
     *
     * @var int
     */
    const POSITION_LEFT = 4;

    /**
     * Value of $titlePosition for when it's at the center.
     *
     * @var int
     */
    const POSITION_CENTER = 5;

    /**
     * Value of $titlePosition for when it's at the right.
     *
     * @var int
     */
    const POSITION_RIGHT = 6;

    /**
     * Value of $titlePosition for when it's at the bottom left.
     *
     * @var int
     */
    const POSITION_BOTTOMLEFT = 1;

    /**
     * Value of $titlePosition for when it's at the bottom.
     *
     * @var int
     */
    const POSITION_BOTTOM = 2;

    /**
     * Value of $titlePosition for when it's at the bottom right.
     *
     * @var int
     */
    const POSITION_BOTTOMRIGHT = 3;

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
    protected $titlePosition = self::POSITION_TOP;

    /**
     * @var int
     */
    protected $backgroundPosition = self::POSITION_CENTER;

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
     * @return int
     */
    public function getBackgroundPosition()
    {
        return $this->backgroundPosition;
    }

    /**
     * @param  int $backgroundPosition
     *
     * @return $this
     */
    public function setBackgroundPosition($backgroundPosition)
    {
        $this->backgroundPosition = $backgroundPosition;
        return $this;
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
    public function isTitlePositionTopLeft()
    {
        return $this->getTitlePosition() == self::POSITION_TOPLEFT;
    }

    /**
     * @return bool
     */
    public function isTitlePositionTop()
    {
        return $this->getTitlePosition() == self::POSITION_TOP;
    }

    /**
     * @return bool
     */
    public function isTitlePositionTopRight()
    {
        return $this->getTitlePosition() == self::POSITION_TOPRIGHT;
    }

    /**
     * @return bool
     */
    public function isTitlePositionLeft()
    {
        return $this->getTitlePosition() == self::POSITION_LEFT;
    }

    /**
     * @return bool
     */
    public function isTitlePositionCenter()
    {
        return $this->getTitlePosition() == self::POSITION_CENTER;
    }

    /**
     * @return bool
     */
    public function isTitlePositionRight()
    {
        return $this->getTitlePosition() == self::POSITION_RIGHT;
    }

    /**
     * @return bool
     */
    public function isTitlePositionBottomLeft()
    {
        return $this->getTitlePosition() == self::POSITION_BOTTOMLEFT;
    }

    /**
     * @return bool
     */
    public function isTitlePositionBottom()
    {
        return $this->getTitlePosition() == self::POSITION_BOTTOM;
    }

    /**
     * @return bool
     */
    public function isTitlePositionBottomRight()
    {
        return $this->getTitlePosition() == self::POSITION_BOTTOMRIGHT;
    }

    /**
     * What's the style for this element's title's position?
     *
     * @return string
     */
    public function getTitlePositionStyle()
    {
        switch ($this->getTitlePosition()) {
            case self::POSITION_TOPLEFT:
                return 'text-top text-left';
            case self::POSITION_TOP:
                return 'text-top text-center';
            case self::POSITION_TOPRIGHT:
                return 'text-top text-right';
            case self::POSITION_LEFT:
                return 'text-middle text-left';
            case self::POSITION_CENTER:
                return 'text-middle text-center';
            case self::POSITION_RIGHT:
                return 'text-middle text-right';
            case self::POSITION_BOTTOMLEFT:
                return 'text-bottom text-left';
            case self::POSITION_BOTTOM:
                return 'text-bottom text-center';
            case self::POSITION_BOTTOMRIGHT:
                return 'text-bottom text-right';
            default:
                // Top
                return 'text-top text-center';
        }
    }

    /**
     * @return bool
     */
    public function isBackgroundPositionTopLeft()
    {
        return $this->getBackgroundPosition() == self::POSITION_TOPLEFT;
    }

    /**
     * @return bool
     */
    public function isBackgroundPositionTop()
    {
        return $this->getBackgroundPosition() == self::POSITION_TOP;
    }

    /**
     * @return bool
     */
    public function isBackgroundPositionTopRight()
    {
        return $this->getBackgroundPosition() == self::POSITION_TOPRIGHT;
    }

    /**
     * @return bool
     */
    public function isBackgroundPositionLeft()
    {
        return $this->getBackgroundPosition() == self::POSITION_LEFT;
    }

    /**
     * @return bool
     */
    public function isBackgroundPositionCenter()
    {
        return $this->getBackgroundPosition() == self::POSITION_CENTER;
    }

    /**
     * @return bool
     */
    public function isBackgroundPositionRight()
    {
        return $this->getBackgroundPosition() == self::POSITION_RIGHT;
    }

    /**
     * @return bool
     */
    public function isBackgroundPositionBottomLeft()
    {
        return $this->getBackgroundPosition() == self::POSITION_BOTTOMLEFT;
    }

    /**
     * @return bool
     */
    public function isBackgroundPositionBottom()
    {
        return $this->getBackgroundPosition() == self::POSITION_BOTTOM;
    }

    /**
     * @return bool
     */
    public function isBackgroundPositionBottomRight()
    {
        return $this->getBackgroundPosition() == self::POSITION_BOTTOMRIGHT;
    }

    /**
     * What's the style for this element's background position?
     *
     * @return string
     */
    public function getBackgroundPositionStyle()
    {
        switch ($this->getBackgroundPosition()) {
            case self::POSITION_TOPLEFT:
                return 'background-top background-left';
            case self::POSITION_TOP:
                return 'background-top background-center';
            case self::POSITION_TOPRIGHT:
                return 'background-top background-right';
            case self::POSITION_LEFT:
                return 'background-middle background-left';
            case self::POSITION_CENTER:
                return 'background-middle background-center';
            case self::POSITION_RIGHT:
                return 'background-middle background-right';
            case self::POSITION_BOTTOMLEFT:
                return 'background-bottom background-left';
            case self::POSITION_BOTTOM:
                return 'background-bottom background-center';
            case self::POSITION_BOTTOMRIGHT:
                return 'background-bottom background-right';
            default:
                // Center
                return 'background-middle background-center';
        }
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
