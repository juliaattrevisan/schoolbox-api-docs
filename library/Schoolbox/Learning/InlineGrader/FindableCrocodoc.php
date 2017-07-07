<?php
/**
 * FindableCrocodoc
 *
 * Anything implementing this interface has access to find a Response
 * via a crocodoc id
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\InlineGrader;

use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Model as SubmissionFile;

interface FindableCrocodoc
{

    /**
     * Find a particular record that contains $crocodocId
     *
     * @param $crocodocId
     * @return SubmissionFile|null
     */
    public function findByCrocodocId($crocodocId);
}