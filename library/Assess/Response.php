<?php
/**
 * Assess_Response
 *
 * Responses are managed here
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
class Assess_Response extends Evaluation_Response {
    public $_table = 'assess_response';
    public $_index = 'response_id';

    /* A const to determine whether to randomise questions
     */
    const RANDOMISE_QUESTIONS = 1;
    const ORDERED_QUESTIONS = 2;
}
