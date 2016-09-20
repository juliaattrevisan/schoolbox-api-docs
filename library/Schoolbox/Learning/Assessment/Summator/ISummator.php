<?php
/**
 * ISummation
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Learning\Assessment\Summator;

use \Schoolbox\Collection;
use \Schoolbox\User;

interface ISummator
{
    /**
     * Execute summation strategy upon a Collection of
     * IAssessable instances
     *
     * @param Collection $allAssessables
     * @param User $user
     * @param bool $publishedOnly
     * @return float|null
     */
    public function summate(Collection $allAssessables, User $user, $publishedOnly);
}