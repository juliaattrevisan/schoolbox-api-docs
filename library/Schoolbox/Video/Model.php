<?php
/**
 * This model represents a video storage in OTFS and its associated thumbnail/poster (if they've been generated)
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   2017 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

namespace Schoolbox\Video;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Resource\Storage\Model as Storage;

class Model implements ISoftDeletable
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /** @var Storage $video */
    protected $video;

    /** @var Storage|null $poster */
    protected $poster;

    /** @var Storage|null $thumbnail */
    protected $thumbnail;

    /**
     * @return Storage
     */
    public function getVideo()
    {
        return $this->video;
    }

    /**
     * @param Storage $video
     *
     * @return self|$this
     */
    public function setVideo(Storage $video)
    {
        $this->video = $video;

        return $this;
    }

    /**
     * @return Storage
     */
    public function getPoster()
    {
        return $this->poster;
    }

    /**
     * @param Storage $poster
     *
     * @return self|$this
     */
    public function setPoster(Storage $poster)
    {
        $this->poster = $poster;

        return $this;
    }

    /**
     * @return Storage
     */
    public function getThumbnail()
    {
        return $this->thumbnail;
    }

    /**
     * @param Storage $thumbnail
     *
     * @return self|$this
     */
    public function setThumbnail(Storage $thumbnail)
    {
        $this->thumbnail = $thumbnail;

        return $this;
    }
}
