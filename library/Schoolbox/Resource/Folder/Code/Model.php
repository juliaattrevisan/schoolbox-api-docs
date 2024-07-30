<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Folder\Code;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreateCollection;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Resource\Folder;

class Model implements ISoftDeletable
{
    use CreateCollection;
    use DeletedAtDate;

    // Maximum length allowed for folder codes
    public const CODE_LENGTH_MAX = 64;

    /**
     * @var Folder\Model
     */
    protected $folder;

    /**
     * @var string
     */
    protected $code;

    /**
     * @param  Folder\Model $folder
     * @return $this
     */
    public function setFolder($folder)
    {
        $this->folder = $folder;
        return $this;
    }

    /**
     * @return Folder\Model
     */
    public function getFolder()
    {
        return $this->folder;
    }

    /**
     * @param  string $code
     * @return $this
     */
    public function setCode($code)
    {
        $this->code = $code;
        return $this;
    }

    /**
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }
}
