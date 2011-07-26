<?php
/**
 * Builder for Question Types. This class spawns the various QuestionType and
 * AnswerType models (handlers) depending on a type_id.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */

class AssessFactory extends DeprecatedModel {
    protected $questionLibraryPath = '../library/assess/questionType';
    protected $questionPrefix = 'AssessQuestion';

    protected $answerLibraryPath = '../library/assess/answerType';
    protected $answerPrefix = 'AssessAnswer';

    /**
     * Spawn a Question type.
     *
     * @param <string> $type, the type of Question
     */
    public function createQuestionModel($type_id) {
        return $this->spawn($type_id, $this->questionLibraryPath, $this->questionPrefix);
    }

    /**
     * Spawn an Answer type.
     *
     * @param <string> $type, the type of Answer
     */
    public function createAnswerModel($type_id) {
        return $this->spawn($type_id, $this->answerLibraryPath, $this->answerPrefix);
    }

    /**
     * Spawn a model for either Questions or Answers.
     *
     * @param <string> $type
     * @param <string> $path
     * @param <string> $prefix
     * @return class
     */
    public function spawn($type_id, $path, $prefix) {
        if (is_numeric($type_id)) {
            $type = $this->getQuestionType($type_id);

            if ($type) {
                $file = $_SERVER['DOCUMENT_ROOT'] . "/$path/{$type}.php";
                $class = $prefix . ucfirst($type);

                /* Instantiate specific question model
                 */
                if (!is_readable($file)) {
                    error();
                }

                require_once($file);

                return new $class;
            }
            else {
                error();
            }
        }
        else {
            error();
        }

        return false;
    }

    /**
     * Find out which file to include
     */
    public function getQuestionType($type_id) {
        $query = "SELECT filename
                  FROM assess_luQuestionType
                  WHERE type_id = '$type_id'";

        return $this->db->getOne($query);
    }

    /**
     * Find out the moodle name
     */
    public function getMoodleQuestionType($type_id) {
        $query = "SELECT moodle
                  FROM assess_luQuestionType
                  WHERE type_id = '$type_id'";

        return $this->db->getOne($query);
    }
}
?>
