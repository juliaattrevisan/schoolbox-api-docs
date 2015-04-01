<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Submission;


use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Mapper;
use Schoolbox\Resource\Storage;
use Schoolbox\UserMapper;

abstract class AbstractMapper extends Mapper
{
    /** @var Storage\Mapper */
    protected $storageMapper;


    /**
     * @return Storage\Mapper
     */
    public function getStorageMapper()
    {
        return $this->storageMapper ?: Storage\Mapper::getInstance(); // TODO remove dingleton
    }


    /**
     * @param Storage\Mapper $storageMapper
     * @return static
     */
    public function setStorageMapper($storageMapper)
    {
        $this->storageMapper = $storageMapper;
        return $this;
    }


    /**
     * Setup method. Implementing classes to define their SQL map here.
     *
     * @return void
     */
    protected function mapping()
    {
        $this->hasTable('student_submission');

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('marked', self::TYPE_BOOLEAN);
        $this->uses('late', self::TYPE_BOOLEAN);
        $this->uses('submitted', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);

        $this->joins('box_id', SubmissionBoxMapper::class, 'id', null, null, 'submissionBox');
        $this->joins('owner', UserMapper::class);
    }

} 