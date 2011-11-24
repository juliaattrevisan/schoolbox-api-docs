<?php
/**
 * AssessFactory
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

class AssessFactory extends EvaluationFactory {
	protected $factoryName = 'Assess';

    protected $questionSubclassPath = '../library/assess/questionType';
    protected $answerSubclassPath = '../library/assess/answerType';
}
