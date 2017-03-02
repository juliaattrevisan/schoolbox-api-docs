<?php
/**
 * Evaluation_Factory
 *
 * Builder for Question Types. This class spawns the various QuestionType and
 * AnswerType models (handlers) depending on a type_id.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
abstract class Evaluation_Factory extends DeprecatedModel
{
    protected $questionPrefix = 'Question';
    protected $answerPrefix = 'Answer';

    /**
     * @return Evaluation_Response
     */
    abstract protected function getResponseHandler();

    /**
     * @return Evaluation_Question
     */
    abstract protected function getQuestionHandler();

    /**
     * @return Evaluation_Section
     */
    abstract protected function getSectionHandler();

    /**
     * Spawn a Question type.
     *
     * @param  $type_id
     * @return Evaluation_Question
     */
    public function createQuestionModel($type_id)
    {
        $fullClassName = $this->getFullClassName($type_id, $this->questionPrefix);
        if(!$fullClassName){
            error(133);
        }
        return new $fullClassName($this->getSectionHandler());
    }

    /**
     * Spawn an Answer type.
     *
     * @param  $type_id
     * @return Evaluation_Answer
     */
    public function createAnswerModel($type_id)
    {
        $fullClassName = $this->getFullClassName($type_id, $this->answerPrefix);
        if(!$fullClassName){
            error(133);
        }
        return new $fullClassName($this->getResponseHandler(), $this->getQuestionHandler());
    }

    /**
     * Gets the class name given this question's type and the prefix (question
     * or answer).
     *
     * @param $type_id
     * @param $prefix
     *
     * @return bool|string
     */
    protected function getFullClassName($type_id, $prefix)
    {
        if(!is_numeric($type_id)){
            return false;
        }

        $className = $this->getQuestionClass($type_id);
        if ($className) {
            $fullClassName = $this->getFactoryName() . '_' . $prefix . '_' . $className;
            if (class_exists($fullClassName)) {
                return $fullClassName;
            }
        }
        return false;
    }

    /**
     * Gets whether this question type is available - it is if its question and
     * answer classes both exist.
     *
     * @param $type_id
     *
     * @return bool
     */
    public function isQuestionTypeAvailable($type_id) {
        return $this->getFullClassName($type_id, $this->answerPrefix)
            && $this->getFullClassName($type_id, $this->questionPrefix);
    }

    /**
     * Find out which html file to use
     *
     * @param  $type_id
     * @return string
     */
    public function getQuestionType($type_id)
    {
        $query = "SELECT filename
                  FROM assess_luQuestionType
                  WHERE type_id = " . $this->db->smartQuote($type_id);

        return $this->db->getOne($query);
    }

    /**
     * Find out which php class file to use
     *
     * @param  $type_id
     * @return string
     */
    public function getQuestionClass($type_id)
    {
        $query = "SELECT classname
                  FROM assess_luQuestionType
                  WHERE type_id = " . $this->db->smartQuote($type_id);

        return $this->db->getOne($query);
    }

    /**
     * Find out the moodle name
     *
     * @param  $type_id
     * @return string|null
     */
    public function getMoodleQuestionType($type_id)
    {
        $query = "SELECT moodle
                  FROM assess_luQuestionType
                  WHERE type_id = " . $this->db->smartQuote($type_id);

        return $this->db->getOne($query);
    }

    /**
     * @return string
     */
    protected abstract function getFactoryName();
}
