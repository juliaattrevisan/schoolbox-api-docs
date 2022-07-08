<?php

/**
 * Evaluation_Question
 *
 * All QuestionTypes inherit from this class. At time of
 * writing, there is nothing needed in the children QuestionType classes.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
abstract class Evaluation_Question extends DeprecatedModel
{

    /**
     * @var string
     */
    public $_table = 'assess_question';

    /**
     * @var string
     */
    public $_index = 'question_id';

    /**
     * @var Evaluation_Section
     */
    private $sectionHandler;

    /**
     * Allowed fields to go into the db
     *
     * @var array
     */
    protected $allowedFields = array('section_id', 'question_id', 'sequence', 'type_id', 'marks', 'question', 'config', 'deleted_at');


    /**
     * Creates a Question.
     *
     * @param array $object
     * @return bool|int
     */
    public function create($object)
    {
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
     * @param array $object
     * @return void
     */
    public function update($object)
    {
        $question = array_intersect_key($object, array_flip($this->allowedFields));

        parent::update($question);
    }

    /**
     * For a new Question, find out what its sequence should be. If it's the
     * first Question for an Assess, return 1
     *
     * @param int $section_id
     * @return int
     */
    public function getNextSequence($section_id)
    {
        $query = "SELECT MAX(sequence)+1 AS num
                  FROM {$this->_table}
                  WHERE section_id = " . $this->db->smartQuote($section_id) . "
                  AND deleted_at IS NULL";

        $sequence = $this->db->getOne($query);

        if ($sequence) {
            return $sequence;
        }

        return 1;
    }

    /**
     * Get all available question types.
     *
     * @return array|bool
     */
    public function getAllQuestionTypes()
    {
        $query = "SELECT *
                  FROM assess_luQuestionType
                  WHERE availability = 'all'
                  OR availability = 'assess'
                  ORDER BY type_id";

        return $this->db->getAll($query);
    }

    /**
     * Get a specific question type
     *
     * @param int $type_id
     * @return array|bool
     */
    public function getQuestionType($type_id)
    {
        $query = "SELECT *
                  FROM assess_luQuestionType
                  WHERE type_id = " . $this->db->smartQuote($type_id);

        return $this->db->getRow($query);
    }


    /**
     * Get all the questions in an assess
     *
     * @param int|string $assess_id
     * @return array|bool
     */
    public function getAll($assess_id = '')
    {
        $query = "
            SELECT
                assess_section.section_id,
                assess_section.sequence AS section_sequence,
                assess_section.title AS section_title,
                assess_question.question_id AS question_id,
                assess_question.type_id AS question_type_id,
                assess_question.sequence AS question_sequence,
                assess_question.marks AS question_marks,
                assess_question.question AS question
            FROM assess
            INNER JOIN assess_section ON assess.assess_id = assess_section.assess_id
            INNER JOIN assess_question ON assess_section.section_id = assess_question.section_id
            WHERE assess.assess_id = " . $this->db->smartQuote($assess_id) . "
            AND assess_question.deleted_at IS NULL
            AND assess_section.deleted_at IS NULL
            ORDER BY assess_section.sequence, assess_question.sequence
        ";

        return $this->db->getAll($query);
    }

    /**
     * Get a list of questions in a section
     *
     * @param int $section_id
     * @return array|bool
     */
    public function getList($section_id)
    {
        $query = "SELECT *
                  FROM {$this->_table}
                  WHERE section_id = " . $this->db->smartQuote($section_id) . "
                  AND deleted_at IS NULL
                  ORDER BY sequence";

        return $this->db->getAll($query);
    }

    /**
     * Reorders a section's questions when a question has been deleted.
     *
     * @param int $assess_id
     * @param int $section_id
     * @return void
     */
    public function resequenceQuestionsAfterDelete($assess_id, $section_id)
    {
        $allQuestions = $this->sectionHandler->getContents($assess_id, $section_id, true);

        if (is_array($allQuestions) && count($allQuestions)) {
            $newSequence = 1;

            /* Resequence non-deleted questions
             */
            foreach ($allQuestions as $currentQuestion) {
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

    /**
     * Find the number of questions in this assess
     *
     * @param int $assess_id
     * @return mixed
     */
    public function numQuestions($assess_id)
    {
        $query = "SELECT COUNT(assess_question.question_id) AS numQuestions
                  FROM assess
                  LEFT JOIN assess_section ON assess.assess_id = assess_section.assess_id
                  LEFT JOIN assess_question ON assess_section.section_id = assess_question.section_id
                  WHERE assess_question.deleted_at IS NULL
                  AND assess_section.deleted_at IS NULL
                  AND assess.assess_id = " . $this->db->smartQuote($assess_id);

        return $this->db->getOne($query);
    }

    /**
     * Reorder questions via jquery sortable
     *
     * @param int $quizId
     * @param int $sectionId
     * @param array $allIds
     */
    public function reorderQuestions(int $quizId, int $sectionId, array $allIds): void
    {
        if (is_array($allIds) && count($allIds)) {
            $sequence = 1;

            foreach ($allIds as $currentId) {
                /* Skip any $currentIds that aren't truly numeric,
                 * in case someone randomly subverted the data payload from frontend
                 *
                 * Arguably someone can subvert the payload to contain "1e3" which is considered
                 * numeric but that shouldn't correspond to 1000; it's can't be a system-generated value
                 */
                if ((int)preg_replace('/[^0-9]*/', '', $currentId) !== (int)$currentId) {
                    continue;
                }

                $this->db->query("
                    UPDATE {$this->_table}
                    JOIN assess_section on assess_section.section_id = {$this->_table}.section_id
                    SET {$this->_table}.sequence = {$sequence}
                    WHERE question_id = {$currentId} 
                    AND {$this->_table}.section_id = {$sectionId} 
                    AND assess_id = {$quizId}
                    AND {$this->_table}.deleted_at IS NULL
                ");

                $sequence++;
            }
        }
    }

    /* Getters/Setters
     */

    /**
     * Set allowed fields
     *
     * @param array $allowedFields
     */
    public function setAllowedFields($allowedFields)
    {
        $this->allowedFields = $allowedFields;
    }


    /**
     * @param Evaluation_Section $sectionHandler
     * @return static
     */
    public function setSectionHandler(Evaluation_Section $sectionHandler)
    {
        $this->sectionHandler = $sectionHandler;
        return $this;
    }
}
