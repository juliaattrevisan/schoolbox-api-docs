<?php

/**
 * Survey_Question
 *
 * All QuestionTypes inherit from this class. At time of
 * writing, there is nothing needed in the children QuestionType classes.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Survey_Question extends Evaluation_Question
{



    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->setSectionHandler(new Survey_Section());
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
                  OR availability = 'survey'
                  ORDER BY type_id";
        return $this->db->getAll($query);
    }
}
