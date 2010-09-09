<?php
require_once '../include/model.php';

class AssessQuestion extends Model {
    public $_table = 'assess_question';
    public $_index = 'question_id';

    private $allowedFields = array('section_id', 'question_id', 'sequence', 'type_id', 'marks', 'question', 'deleted_at');

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

    /**
     * Gets the last question entered into a particular section.
     *
     * @param integer $section_id
     * @return array
     */
    function getLast($section_id) {
        $query = "SELECT *
            FROM `$this->_table`
            WHERE `section_id` = $section_id
            AND deleted_at IS NULL
            ORDER BY `order`
            DESC LIMIT 1";

        return $this->db->getRow($query);
    }

    /**
     * For a new Question, find out what its sequence should be. If it's the
     * first Question for an Assess, return 0
     */
    function getNextSequence($section_id) {
        $query = "SELECT MAX(sequence)+1 AS num
                  FROM {$this->_table}
                  WHERE section_id = '$section_id'
                  AND deleted_at IS NULL";

        $sequence = $this->db->getOne($query);

        if ($sequence) {
            return $sequence['num'];
        }

        return 1;
    }

    /**
     * Get all available question types.
     *
     * @return <type>
     */
    function getAllQuestionTypes() {
        $query = "SELECT *
                  FROM assess_luQuestionType
                  ORDER BY type_id";

        return $this->db->getAll($query);
    }

    /**
     * Get a specific question type
     * 
     * @param <type> $type_id
     * @return <type>
     */
    function getQuestionType($type_id) {
        $query = "SELECT *
                  FROM assess_luQuestionType
                  WHERE type_id = '$type_id'";

        return $this->db->getRow($query);
    }

    /**
     * Get a list of questions in a section
     * 
     * @param <type> $section_id
     * @return <type>
     */
    function getList($section_id) {
        $query = "SELECT *
                  FROM {$this->_table}
                  WHERE section_id = '$section_id'
                  AND deleted_at IS NULL
                  ORDER BY sequence";

        return $this->db->getAll($query);
    }

    /**
     * Reorders a section's questions when a question has been deleted.
     *
     * @param <type> $section_id
     */
    function resequenceQuestionsAfterDelete($assess_id, $section_id) {
        $sectionHandler = new AssessSection();

        $allQuestions = $sectionHandler->getContents($assess_id, $section_id, true);

        if(is_array($allQuestions) && count($allQuestions)) {
            $newSequence = 1;

            /* Resequence non-deleted questions
             */
            foreach($allQuestions as $currentQuestion) {
                if ($currentQuestion['deleted_at'] == null) {
                    $updatedQuestion = array(
                        'question_id' => $currentQuestion['question_id'],
                        'sequence' => $newSequence,
                    );

                    parent::update($updatedQuestion);

                    $newSequence++;
                }
            }
        }
    }
}
?>
