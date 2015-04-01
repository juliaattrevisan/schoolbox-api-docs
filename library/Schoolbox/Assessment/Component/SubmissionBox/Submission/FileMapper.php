<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Submission;

use Schoolbox\Resource\Storage;

class FileMapper extends AbstractMapper
{

    /**
     * @return void
     */
    protected function mapping()
    {
        parent::mapping();

        $file = new File();
        $file->setStorage($this->getStorageMapper()->createModel());

        $this->hasPrototype($file);

        $this->uses('realname', self::TYPE_STRING, null, 'filename');
        $this->uses('uuid', self::TYPE_STRING);

        $this->joins('hash', Storage\Mapper::class, 'hash', null, null, 'storage', 'storage');
    }

} 