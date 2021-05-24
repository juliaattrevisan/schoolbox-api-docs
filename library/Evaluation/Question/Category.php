<?php

/**
 * Evaluation_Question_Category
 *
 * Abstract interface for all question bank categories
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
abstract class Evaluation_Question_Category extends DeprecatedModel
{
    /**
     * @var string
     */
    public $_table = 'assess_question_category';

    /**
     * @var string
     */
    public $_index = 'question_category_id';

    /**
     * @var string
     */
    protected $availability = '';

    /**
     * Get a list of QuestionCategories to chooze from
     *
     * @param int $parent_id
     * @return array|bool
     */
    public function getList($parent_id = 0)
    {
        $query = "SELECT *
                  FROM {$this->_table}
                  WHERE availability = '{$this->getAvailability()}'
                  AND parent_id = " . $this->db->smartQuote($parent_id) . "
                  ORDER BY name";

        return $this->db->getAll($query);
    }

    /**
     * Get a list of Question Categories to chooze from along with
     * the number of questions in them
     *
     * @param int $parent_id
     * @return array|bool
     */
    public function getListWithQuestionCount($parent_id = 0)
    {
        $query = "SELECT
                    {$this->_table}.*,
                    COUNT(assess_question_bank.question_category_id) AS numQuestions
                  FROM {$this->_table}
                  LEFT JOIN assess_question_bank
                    ON {$this->_table}.question_category_id = assess_question_bank.question_category_id
                    AND assess_question_bank.deleted_at IS NULL
                  WHERE availability = '{$this->getAvailability()}'
                  AND parent_id = " . $this->db->smartQuote($parent_id) . "
                  AND {$this->_table}.deleted_at IS NULL
                  GROUP BY question_category_id
                  ORDER BY name";

        return $this->db->getAll($query);
    }

    /**
     * Wrapper method which defaults a parent_id to the root level
     * if not explicitly specified
     *
     * @param int $parent_id
     * @return array
     */
    public function getPath($parent_id = 0)
    {
        return $this->getPathSegment($parent_id);
    }

    /**
     * Recursively generate a list of Question Bank categories
     *
     * @param int $parent_id
     * @return array
     */
    private function getPathSegment($parent_id)
    {
        $all = array();

        $allCategories = $this->getListWithQuestionCount($parent_id);

        if (is_array($allCategories) && count($allCategories)) {
            foreach ($allCategories as $currentCategory) {
                $record = $currentCategory;

                $record['children'] = $this->getPathSegment($currentCategory['question_category_id']);

                $all[] = $record;
            }
        }

        return $all;
    }

    /**
     * Used in conjunction with javascript tree
     *
     * @param int $targetId
     * @param int $destinationId
     * @param string|null $position
     *
     * @internal param $target
     * @internal param $destination
     */
    public function move($targetId, $destinationId, $position)
    {
        if (is_numeric($targetId) && is_numeric($destinationId)) {
            $destinationNode = $this->getByID($destinationId);
            $targetNode = $this->getByID($targetId);

            /* Move target to parent of destination
            */
            if (($position == 'before') || ($position == 'after')) {
                $parentNode = $this->getByID($destinationNode['parent_id']);

                /* If you don't unset deleted_at, it gets rendered as 0000-00-00 00:00:00
                 * in the db
                 */
                $targetNode['parent_id'] = $parentNode['question_category_id'];
                unset($targetNode['deleted_at']);

                $this->update($targetNode);
            }
            /* Move target so that destination is the parent of target
             */
            else {
                /* If you don't unset deleted_at, it gets rendered as 0000-00-00 00:00:00
                 * in the db
                 */
                $targetNode['parent_id'] = $destinationNode['question_category_id'];
                unset($targetNode['deleted_at']);

                $this->update($targetNode);
            }
        }
    }

    /**
     * Create a new Questionbank Category, injecting $availability
     *
     * @param array $data
     */
    public function create($data)
    {
        $data['availability'] = $this->getAvailability();
        $data['created_at'] = 'NOW()';
        $data['updated_at'] = 'NOW()';

        return parent::create($data);
    }

    /**
     * Softdeletes a Questionbank Category
     *
     * @param array $data
     */
    public function delete($data)
    {
        $data['deleted_at'] = 'NOW()';

        return parent::update($data);
    }

    /* Getters/Setters
      */

    /**
     * Get availability
     *
     * @return string
     */
    protected function getAvailability()
    {
        return $this->availability;
    }
}
