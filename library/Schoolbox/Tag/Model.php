<?php
/**
 * Content tag model
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 
namespace Schoolbox\Tag;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model;

class Model implements ISoftDeletable
{
    use Model\Id;
    use Model\CreatedDate;
    use Model\ModifiedDate;
    use Model\DeletedDate;

    /**
     * What type of content does this relate to?
     *
     * This should correspond to a mapper name?
     *
     * @var string
     */
    protected $contentType;

    /**
     * What's the identifier for the content?
     *
     * This + $contentType identify the item this tag belongs to.
     *
     * @var int
     */
    protected $contentId;

    /**
     * What type of tag is this?
     *
     * Example tag types: curriculum
     *
     * @var string
     */
    protected $tagType;

    /**
     * Tag name or tag ID?
     *
     * @var int
     */
    protected $tagId;

    /**
     * @return string
     */
    public function getContentType()
    {
        return $this->contentType;
    }

    /**
     * @param  string $contentType
     *
     * @return $this
     */
    public function setContentType($contentType)
    {
        $this->contentType = $contentType;
        return $this;
    }

    /**
     * @return int
     */
    public function getContentId()
    {
        return $this->contentId;
    }

    /**
     * @param  int $contentId
     *
     * @return $this
     */
    public function setContentId($contentId)
    {
        $this->contentId = $contentId;
        return $this;
    }

    /**
     * @return string
     */
    public function getTagType()
    {
        return $this->tagType;
    }

    /**
     * @param  string $tagType
     *
     * @return $this
     */
    public function setTagType($tagType)
    {
        $this->tagType = $tagType;
        return $this;
    }

    /**
     * @return int
     */
    public function getTagId()
    {
        return $this->tagId;
    }

    /**
     * @param  int $tagId
     *
     * @return $this
     */
    public function setTagId($tagId)
    {
        $this->tagId = $tagId;
        return $this;
    }
}
