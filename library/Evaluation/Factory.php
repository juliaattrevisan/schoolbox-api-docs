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
    protected $factoryName = 'Assess';

    protected $questionPrefix = 'Question';
    protected $answerPrefix = 'Answer';

    /**
     * Spawn a Question type.
     *
     * @param  $type_id
     * @return class
     */
    public function createQuestionModel($type_id)
    {
        return $this->spawn($type_id, $this->questionPrefix);
    }

    /**
     * Spawn an Answer type.
     *
     * @param  $type_id
     * @return class
     */
    public function createAnswerModel($type_id)
    {
        return $this->spawn($type_id, $this->answerPrefix);
    }

    /**
     * Spawn a model for either Questions or Answers.
     *
     * @param  $type_id
     * @param  $prefix
     * @return bool
     */
    public function spawn($type_id, $prefix)
    {
        if (is_numeric($type_id)) {
            $object = $this->getFullClassName($type_id, $prefix);
            if ($object && class_exists($object)) {
                return new $object;
            }
        }
        error(133);

        return false;
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
    protected function getFullClassName($type_id, $prefix) {
        $classname = $this->getQuestionClass($type_id);
        if ($classname) {
            $object = $this->factoryName . '_' . $prefix . '_' . $classname;
            if (class_exists($object)) {
                return $object;
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
     * @return
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
     * @return
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
     * @return
     */
    public function getMoodleQuestionType($type_id)
    {
        $query = "SELECT moodle
                  FROM assess_luQuestionType
                  WHERE type_id = " . $this->db->smartQuote($type_id);

        return $this->db->getOne($query);
    }
}
