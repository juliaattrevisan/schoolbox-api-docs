<?php
/**
 * Evaluation_Question
 *
 * All QuestionTypes inherit from this class. At time of
 * writing, there is nothing needed in the children QuestionType classes.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
abstract class Evaluation_Question extends DeprecatedModel
{
    public $_table = 'assess_question';
    public $_index = 'question_id';

    private $allowedFields = array('section_id', 'question_id', 'sequence', 'type_id', 'marks', 'question', 'deleted_at');

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::DeprecatedModel();
    }

    /**
     * Creates a Question.
     *
     * @param  $object
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
     * @param  $object
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
     * @param  $section_id
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
     * @param  $type_id
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
     * Whether a question type has an applicable legend
     *
     * @param  $type_id
     * @return array|bool
     */
    public function hasLegend($type_id)
    {
        $query = "SELECT *
				  FROM assess_luQuestionType
				  WHERE type_id = " . $this->db->smartQuote($type_id) . "
				  AND has_legend = 'true'";

        return $this->db->getRow($query);
    }

    /**
     * Get all the questions in an assess
     *
     * @param $assess_id
     * @return array|bool
     */
    public function getAll($assess_id)
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
			ORDER BY assess_section.sequence, assess_question.sequence
		";

        return $this->db->getAll($query);
    }

    /**
     * Get a list of questions in a section
     *
     * @param  $section_id
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
     * @param  $assess_id
     * @param  $section_id
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
     * @param  $assess_id
     *
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
}
