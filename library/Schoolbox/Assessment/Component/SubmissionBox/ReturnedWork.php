<?php
/**
 * Model for teacher returns for student submissions into submission boxes.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox;

use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\File\Mimetype;
use Schoolbox\FileInterface;
use Schoolbox\IConfigLoader;
use Schoolbox\ISoftDeletable;
use Schoolbox\User;

class ReturnedWork implements ISoftDeletable, FileInterface
{

    /**
     * @var int
     */
    protected $id;

    /**
     * @var SubmissionBox
     */
    protected $submission_box;

    /**
     * @var User
     */
    protected $marker;

    /**
     * @var User
     */
    protected $owner;

    /**
     * @var string
     */
    protected $comment;

    /**
     * @var string
     */
    protected $mark;

    /**
     * @var float
     */
    protected $normalized_mark;

    /**
     * @var string
     */
    protected $filename;

    /**
     * @var Mimetype
     */
    protected $mimetype;

    /**
     * Size of the file attached to the return
     *
     * @var null
     */
    protected $filesize = null;

    /**
     * @var \Alaress_Date_Format
     */
    protected $submitted;

    /**
     * @var \Alaress_Date_Format
     */
    protected $returned;

    /**
     * Whether this returned work item is softdeleted or not
     *
     * @var
     */
    protected $deleted;

    /**
     * @var string
     */
    protected $temp_location;

    /**
     * @var IConfigLoader
     */
    protected $config_loader;

    /**
     * Optional crocodoc uuid
     *
     * @var null
     */
    protected $uuid = null;

    /**
     * Creates object and sets up related objects.
     */
    public function __construct()
    {
        $this->setSubmissionBox(new SubmissionBox());
        $this->setOwner(new User());
        $this->setConfigLoader(ConfigMapper::getInstance());
    }

    /**
     * Calculates a gradient (from 0 to 10) from the normalized mark for this
     * return.
     *
     * @return int
     */
    public function getGradientFromNormalizedMark()
    {
        return max(0, min(10, (int)($this->getNormalizedMark() / 10)));
    }

    /**
     * Gets the location at which this file would be stored.
     *
     * @return string
     */
    public function getFileLocation()
    {
        $id = $this->getId();
        if (!isset($id)) {
            return false;
        }
        return $this->config_loader->get('returns_dir') . '/' . $id . '.dat';
    }

    /**
     * Set filesize
     *
     * @param $filesize
     * @return $this
     */
    public function setFilesize($filesize)
    {
        $this->filesize = $filesize;
        return $this;
    }

    /**
     * Get filesize
     *
     * @return null
     */
    public function getFilesize()
    {
        return $this->filesize;
    }

    /**
     * Gets the size of this file (from where it's stored on the file system),
     * or false if it doesn't exist.
     *
     * @return bool|int
     */
    public function getFilesizeByPath()
    {
        if (($location = $this->getFileLocation()) !== false) {
            if (file_exists($location)) {
                return filesize($location);
            }
        }
        return false;
    }

    /**
     * Auto-generated methods
     */

    /**
     * @param  string $filename
     *
     * @return $this
     */
    public function setFilename($filename)
    {
        $this->filename = $filename;
        return $this;
    }

    /**
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * @param  int $id
     *
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
     * @param  \Schoolbox\Assessment\Component\SubmissionBox $submission_box
     *
     * @return $this
     */
    public function setSubmissionBox($submission_box)
    {
        $this->submission_box = $submission_box;
        return $this;
    }

    /**
     * @return \Schoolbox\Assessment\Component\SubmissionBox
     */
    public function getSubmissionBox()
    {
        return $this->submission_box;
    }

    /**
     * The person who the mark is for
     * @param  \Schoolbox\User $owner
     *
     * @return $this
     */
    public function setOwner($owner)
    {
        $this->owner = $owner;
        return $this;
    }

    /**
     * The person who the mark is for
     * @return \Schoolbox\User
     */
    public function getOwner()
    {
        return $this->owner;
    }

    /**
     * The person who marked the work
     * @param \Schoolbox\User $marker
     *
     * @return $this
     */
    public function setMarker($marker)
    {
        $this->marker = $marker;
        return $this;
    }

    /**
     * The person who marked the work
     * @return \Schoolbox\User
     */
    public function getMarker()
    {
        return $this->marker;
    }


    /**
     * @param  \Alaress_Date_Format $submitted
     *
     * @return $this
     */
    public function setSubmitted($submitted)
    {
        $this->submitted = $submitted;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getSubmitted()
    {
        return $this->submitted;
    }

    /**
     * @param  string $comment
     *
     * @return $this
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
     * @param  string $mark
     *
     * @return $this
     */
    public function setMark($mark)
    {
        $this->mark = $mark;
        return $this;
    }

    /**
     * @return string
     */
    public function getMark()
    {
        return $this->mark;
    }

    /**
     * @param  float $normalized_mark
     *
     * @return $this
     */
    public function setNormalizedMark($normalized_mark)
    {
        $this->normalized_mark = $normalized_mark;
        return $this;
    }

    /**
     * @return float
     */
    public function getNormalizedMark()
    {
        return $this->normalized_mark;
    }

    /**
     * @param  \Alaress_Date_Format $returned
     *
     * @return $this
     */
    public function setReturned($returned)
    {
        $this->returned = $returned;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getReturned()
    {
        return $this->returned;
    }

    /**
     * @param $config_loader
     *
     * @return $this
     */
    public function setConfigLoader($config_loader)
    {
        $this->config_loader = $config_loader;
        return $this;
    }

    /**
     * @return IConfigLoader
     */
    public function getConfigLoader()
    {
        return $this->config_loader;
    }

    /**
     * @param  Mimetype $mimetype
     *
     * @return $this
     */
    public function setMimetype($mimetype)
    {
        $this->mimetype = $mimetype;
        return $this;
    }

    /**
     * @return Mimetype
     */
    public function getMimetype()
    {
        return $this->mimetype;
    }

    /**
     * Set deleted
     *
     * @param mixed $deleted
     */
    public function setDeleted($deleted)
    {
        $this->deleted = $deleted;
    }

    /**
     * Get deleted
     *
     * @return mixed
     */
    public function getDeleted()
    {
        return $this->deleted;
    }

    // File related methods

    /**
     * @param  string $temp_location
     * @return $this
     */
    public function setTempLocation($temp_location)
    {
        $this->temp_location = $temp_location;
        return $this;
    }

    /**
     * @return string
     */
    public function getTempLocation()
    {
        return $this->temp_location;
    }

    /**
     * Loads the file size for this file, based on the temp location.
     *
     * @return bool
     */
    public function loadFilesizeFromTempLocation()
    {
        if (file_exists($temp_location = $this->getTempLocation())) {
            $this->setFilesize(filesize($temp_location));
            return true;
        }
        return false;
    }

    /**
     * Copies file from its temp location to permanent upload location.
     *
     * @todo Traits would be great here.
     *
     * @return bool
     */
    public function copyFromTempLocation()
    {
        $temp_location = $this->getTempLocation();
        $file_location = $this->getFileLocation();
        if (file_exists($temp_location) && $file_location) {
            return copy($temp_location, $file_location);
        }
        return false;
    }

    /**
     * Whether or not this returned work is softdeleted
     *
     * @return bool
     */
    public function isDeleted()
    {
        if ($this->getDeleted()) {
            return true;
        }

        return false;
    }

    /* Getters/Setters
     */

    /**
     * Set uuid
     *
     * @param null $uuid
     */
    public function setUuid($uuid)
    {
        $this->uuid = $uuid;
    }

    /**
     * Get uuid
     *
     * @return null
     */
    public function getUuid()
    {
        return $this->uuid;
    }


    /**
     * Returns the full mime string for the file
     *
     * @return string
     */
    public function getMime()
    {
        return $this->getMimetype()->getMime();
    }


    /**
     * Returns the size on disk of the file
     *
     * @return int
     */
    public function getSize()
    {
        return $this->getFilesize();
    }

}
