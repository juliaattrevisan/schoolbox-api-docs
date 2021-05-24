<?php

/**
 * Model for submission box mark types.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox;

use Schoolbox;

class MarkType
{

    /* These integer consts reference submission_mark_type.mark_type_id column
     * Some schools have altered their lookup table's contents, causing the consts
     * to desync. I don't know how that resolved itself, but we can't accommodate
     * that usage with the consts below.
     *
     * Most of the swamp uses these consts in an important manner so we can't just
     * delete them; where possible, try to use the slugs in preference to the consts.
     */

    /** @deprecated - Use SLUG_PERCENT */
    public const TYPE_PERCENT    = 1;

    /** @deprecated - Use SLUG_LETTER */
    public const TYPE_LETTER     = 2;

    /** @deprecated - Use SLUG_TOTAL */
    public const TYPE_TOTAL      = 3;

    /** @deprecated - Use SLUG_FREE */
    public const TYPE_FREE       = 4;

    /** @deprecated - Use SLUG_RUBRIC */
    public const TYPE_RUBRIC     = 5;

    /** @deprecated - Use SLUG_NONE */
    public const TYPE_NONE       = 6;

    /** @deprecated - Use SLUG_COMPLETION */
    public const TYPE_COMPLETION = 7;

    /* Use these slugs to lookup instances of MarkType from submission_mark_type
     * in case ids became desynced due to data fudging. There isn't particularly
     * widespread use of this yet, but if you have no choice, fall back to the
     * consts described above.
     */
    public const SLUG_PERCENT    = 'percent';
    public const SLUG_LETTER     = 'letter';
    public const SLUG_TOTAL      = 'raw';
    public const SLUG_FREE       = 'free';
    public const SLUG_RUBRIC     = 'rubric'; // TODO whatttt is this
    public const SLUG_NONE       = 'none';
    public const SLUG_COMPLETION = 'completion';

    /**
     * @var int
     */
    private $id;

    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $slug;

    /**
     * @var string
     */
    private $comment;

    /**
     * @var bool
     */
    private $enabled;

    /**
     * This MarkType is "None"
     *
     * @return bool
     */
    public function isNone(): bool
    {
        return $this->slug === self::SLUG_NONE;
    }

    /**
     * @param string $comment
     * @return static
     */
    public function setComment($comment)
    {
        $this->comment = $comment;
        return $this;
    }

    /**
     * @return string
     */
    public function getComment()
    {
        return $this->comment;
    }

    /**
     * @param boolean $enabled
     * @return static
     */
    public function setEnabled($enabled)
    {
        $this->enabled = $enabled;
        return $this;
    }

    /**
     * @return boolean
     */
    public function getEnabled()
    {
        return $this->enabled;
    }

    /**
     * @param int $id
     * @return static
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
     * @param string $name
     * @return static
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $slug
     * @return static
     */
    public function setSlug($slug)
    {
        $this->slug = $slug;
        return $this;
    }

    /**
     * @return string
     */
    public function getSlug()
    {
        return $this->slug;
    }
}
