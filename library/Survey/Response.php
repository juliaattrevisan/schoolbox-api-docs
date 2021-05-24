<?php

/**
 * Survey_Response
 *
 * Responses are managed here
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Survey_Response extends Evaluation_Response
{


    public $_table = 'assess_response';
    public $_index = 'response_id';
/* A const to determine whether to randomise questions
     */
    const RANDOMISE_QUESTIONS = 1;
    const ORDERED_QUESTIONS = 2;
}
