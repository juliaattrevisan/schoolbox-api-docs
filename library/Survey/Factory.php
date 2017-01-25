<?php
/**
 * Survey_Factory
 *
 * Builder for Question Types. This class spawns the various QuestionType and
 * AnswerType models (handlers) depending on a type_id.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
class Survey_Factory extends Evaluation_Factory
{
    /**
     * @return string
     */
	protected function getFactoryName()
    {
        return 'Survey';
    }
}
