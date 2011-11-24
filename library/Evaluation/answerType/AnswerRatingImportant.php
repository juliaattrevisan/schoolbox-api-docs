<?php
/**
 * SurveyAnswerRatingImportant
 *
 * Handler for rating questions.
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
require_once($_SERVER['DOCUMENT_ROOT'] . '/../library/evaluation/answerType/AnswerRadio.php');

abstract class AnswerRatingImportant extends AnswerRadio {
    const CORRECT_CLASS = 'multiCorrect';
    const INCORRECT_CLASS = 'multiIncorrect';
}
