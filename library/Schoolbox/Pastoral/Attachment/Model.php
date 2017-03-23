<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral\Attachment;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Pastoral\Model as Pastoral;
use Schoolbox\Resource\File\Model as File;
use Schoolbox\Resource\Storage\IStorage;

class Model implements ISoftDeletable {

    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /** @var Pastoral $pastoral */
    protected $pastoral;

    /** @var IStorage $storage */
    protected $storage;
    
    /** @var File $file */
    protected $file;

    /**
     * @param Pastoral $pastoral
     * @return self
     */
    public function setPastoral($pastoral) {
        $this->pastoral = $pastoral;
        return $this;
    }

    /**
     * @return Pastoral
     */
    public function getPastoral() {
        return $this->pastoral;
    }

    /**
     * @param IStorage $storage
     * @return self
     */
    public function setStorage($storage) {
        $this->storage = $storage;
        return $this;
    }

    /**
     * @return IStorage
     */
    public function getStorage() {
        return $this->storage;
    }

    /**
     * @param File $file
     * @return self
     */
    public function setFile($file) {
        $this->file = $file;
        return $this;
    }

    /**
     * @return File
     */
    public function getFile() {
        return $this->file;
    }    
}
