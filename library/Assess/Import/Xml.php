<?php
/**
 * Assess_Import_Xml
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Assess_Import_Xml extends Evaluation_Import_Xml {


    /**
     * Returns TRUE, reflecting fact that a Quiz can count towards final year mark.
     *
     * @return bool
     */
    public function hasWeighting()
    {
        return TRUE;
    }
}
