<?php
require_once '../include/model.php';

class AssessQuestionShort extends AssessQuestion {
    private $allowedFields = array('section_id', 'question_id', 'sequence', 'type_id', 'marks', 'question');

    /**
     * Creates a Question.
     * 
     * @param <type> $object
     * @return <type>
     */
    function create($object) {
        $question = array_intersect_key($object, array_flip($this->allowedFields));

        $question_id = parent::create($question);

        if (!$question_id) {
            return false;
        }

        return $question_id;
    }

    /**
     * Updates a question's details.
     * 
     * @param <type> $object
     */
    function update($object) {
        $question = array_intersect_key($object, array_flip($this->allowedFields));

        parent::update($question);
    }
}
?>
