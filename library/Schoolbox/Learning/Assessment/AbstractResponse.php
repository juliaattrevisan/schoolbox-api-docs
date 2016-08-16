<?php
/**
 * AbstractResponse
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment;

use \Schoolbox\Collection;

class AbstractResponse implements IResponse
{
    /**
     * @var Collection
     */
    protected $allFeedback;

    /**
     * Model constructor.
     */
    public function __construct()
    {
        $this->setAllFeedback(new Collection());
    }

    /* Getters/Setters
     */

    /**
     * @return mixed
     */
    public function getAllFeedback()
    {
        return $this->allFeedback;
    }

    /**
     * @param mixed $allFeedback
     */
    public function setAllFeedback($allFeedback)
    {
        $this->allFeedback = $allFeedback;
    }

}