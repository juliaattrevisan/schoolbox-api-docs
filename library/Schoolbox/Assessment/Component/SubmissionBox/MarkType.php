<?php
/**
 * Model for submission box mark types.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox;

use Schoolbox;

class MarkType
{
    /** @deprecated - Use SLUG_* constants instead */
    const TYPE_PERCENT    = 1;
    const TYPE_LETTER     = 2;
    const TYPE_TOTAL      = 3;
    const TYPE_FREE       = 4;
    const TYPE_RUBRIC     = 5;
    const TYPE_NONE       = 6;
    const TYPE_COMPLETION = 7;

    const SLUG_PERCENT    = 'percent';
    const SLUG_LETTER     = 'letter';
    const SLUG_TOTAL      = 'raw';
    const SLUG_FREE       = 'free';
    const SLUG_RUBRIC     = 'rubric';
    const SLUG_NONE       = 'none';
    const SLUG_COMPLETION = 'completion';

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
