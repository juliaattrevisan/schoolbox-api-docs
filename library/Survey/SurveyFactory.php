<?php
/**
 * SurveyFactory
 *
 * Builder for Question Types. This class spawns the various QuestionType and
 * AnswerType models (handlers) depending on a type_id.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
require_once($_SERVER['DOCUMENT_ROOT'] . '/../library/evaluation/EvaluationFactory.php');

class SurveyFactory extends EvaluationFactory {
	protected $factoryName = 'Survey';

    protected $questionSubclassPath = '../library/survey/questionType';
    protected $answerSubclassPath = '../library/survey/answerType';
}

?>
