<?php
/**
 * Survey_Attempt
 *
 * Assessment Attempts are managed through this class.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Survey_Attempt extends Evaluation_Attempt {
    public $_table = 'assess_attempt';
    public $_index = 'attempt_id';
}
