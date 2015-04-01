<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Submission;


class InlineMapper extends AbstractMapper
{

    /**
     * @return void
     */
    protected function mapping()
    {
        parent::mapping();

        $this->hasPrototype(new Inline());

        $this->uses('body', self::TYPE_STRING);
        $this->uses('draft', self::TYPE_BOOLEAN);
    }

} 