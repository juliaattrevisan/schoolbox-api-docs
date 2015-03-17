<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Attachment\Collector;

use Schoolbox\Resource;
use Schoolbox\Resource\File;

class FileSystemHandler implements TypeHandlerInterface
{

    /** @var File\ILocator */
    protected $locator;

    /** @var File\IFileMapper */
    protected $receiver;


    /**
     * @param File\ILocator    $locator
     * @param File\IFileMapper $receiver
     */
    public function __construct(File\ILocator $locator, File\IFileMapper $receiver)
    {
        $this->locator = $locator;
        $this->receiver = $receiver;
    }


    /**
     * @param string $id
     * @throws Resource\Exception
     * @return File\IFile
     */
    public function fetch($id)
    {
        // if receiver and locator are the same, just return a findById()
        if ($this->receiver instanceof $this->locator) {
            return $this->locator->findById($id);
        }
        // get the storage object from the nominated file, and wrap that in the target class
        return $this->receiver->wrap($this->locator->findById($id)->getStorage());
    }

}