<?php
/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource;

use Schoolbox\File\Mimetype;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Resource\File\IFile;
use Schoolbox\Resource\File\StorableTrait;
use Schoolbox\Resource\Folder\IFolder;
use Schoolbox\Resource\Storage\Model as Storage;
use Schoolbox\User;

/**
 * Class AbstractFile
 * Provides common file-based functionality for files stored in a directory structure (ie. can have an IFolder parent)
 *
 * @package Schoolbox\Resource
 * @method AbstractFile setHash($hash)
 * @method AbstractFile setMimetype(Mimetype $mimetype)
 * @method AbstractFile setSize($size)
 */
abstract class AbstractFile implements IFile, ISoftDeletable
{
    use StorableTrait;
    use CreatedAtDate;
    use DeletedAtDate;

    /** @var string $title */
    protected $title;

    /** @var User $owner */
    protected $owner;

    /** @var IFolder $folder */
    protected $folder;

    /**
     * @return IFolder
     */
    public function getFolder()
    {
        return $this->folder;
    }

    /**
     * @param IFolder $folder
     *
     * @return self
     */
    public function setFolder($folder)
    {
        $this->folder = $folder;

        return $this;
    }

    /**
     * @return User
     */
    public function getOwner()
    {
        return $this->owner;
    }

    /**
     * @param User $owner
     *
     * @return self
     */
    public function setOwner($owner)
    {
        $this->owner = $owner;

        return $this;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param string $title
     *
     * @return self
     */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Act as a proxy for the internal storage object
     *
     * @param string $name
     * @param array  $arguments
     *
     * @return mixed
     */
    function __call($name, $arguments)
    {
        if (method_exists($this->storage, $name)) {
            $value = call_user_func_array([$this->storage, $name], $arguments);
            if ($value instanceof Storage) {
                return $this;
            }

            return $value;
        }
        $trace   = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $pattern = "Call to undefined method <b>%s::%s()</b> in <b>%s</b> on line <b>%d</b>, triggered";
        trigger_error(sprintf($pattern, get_called_class(), $name, $trace[1]['file'], $trace[1]['line']), E_USER_ERROR);

        return null;
    }
}
