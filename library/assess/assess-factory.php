<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/../library/assess/assess-question.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/../library/assess/assess-answer.php');

/**
 * Builder for Question Types
 *
 * @author sc
 */
class AssessFactory extends Model {
    var $questionLibraryPath = '../library/assess/questionType';
    var $questionPrefix = 'AssessQuestion';

    var $answerLibraryPath = '../library/assess/answerType';
    var $answerPrefix = 'AssessAnswer';

    /**
     * Spawn a Question type.
     *
     * @param <string> $type, the type of Question
     */
    function createQuestionModel($type_id) {
        return $this->spawn($type_id, $this->questionLibraryPath, $this->questionPrefix);
    }

    /**
     * Spawn an Answer type.
     *
     * @param <string> $type, the type of Answer
     */
    function createAnswerModel($type_id) {
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
    function spawn($type_id, $path, $prefix) {
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
    function getQuestionType($type_id) {
        $query = "SELECT filename
                  FROM assess_luQuestionType
                  WHERE type_id = '$type_id'";

        return $this->db->getOne($query);
    }
}
?>