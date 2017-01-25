<?php
/**
 * Survey_Answer
 *
 * All AnswerTypes inherit from this class.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
class Survey_Answer extends Evaluation_Answer {
    public function __construct()
    {
        parent::__construct(new Survey_Response(), new Survey_Question());
    }
}

