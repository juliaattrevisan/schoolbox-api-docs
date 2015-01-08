<?php
/**
 * Survey_Answer_Ranking
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class Survey_Answer_Ranking extends Evaluation_Answer_Ranking {
    protected $graphTemplateFile = 'survey/answer/ranking/graph.html';

    /**
     * Setup
     */
    protected function setup() {
        $this->setResponseHandler(new Survey_Response());
    }

    /**
     * Graph the results for this answer type
     *
     * @param  $page
     * @param  $currentQuestion
     * @return void
     */
    public function graphResults($page, $currentQuestion) {
        $componentHandle = 'temp' . $currentQuestion['question_id'];

        $page->setFile($componentHandle, $this->getGraphTemplateFile());

        $questionId = (int)$currentQuestion['question_id'];

        /* Find all the possible things to chooze from
         */
        $answer = $this->getByField('question_id', $questionId);

        $values = unserialize($answer['answer']);

        $allValues = array();

        /* Prefill a "scoreboard" of zeroes for each individual response item
         * This represents each individual response item's position in the stack
         * and how many times it is chosen to be in that position in the stack
         */
        foreach ($values as $currentValue) {
            $allValues[$currentValue->answerRight] = array_fill(0, count($values), 0);
        }

        /* Find all the responses that people made
         */
        $allResults = $this->getResponseHandler()->getAllByField('question_id', $questionId);

        /* Collate each response into $allValues
         */
        foreach ($allResults as $currentResult) {
            $response = unserialize($currentResult['response']);

            for ($i = 0; $i < count($response); $i++) {
                $allValues[$response[$i]][$i]++;
            }
        }

        /* Sort
         */
        foreach ($allValues as &$currentValue) {
            ksort($currentValue);
        }

        /* Find the max value for each individual response, so we can
         * once again order by key
         */
        new \Alaress_Probe($allValues);
        foreach ($allValues as &$currentValue) {
            $max = 0;
            $largestKey = 0;

            for ($i = 0; $i < count($currentValue); $i++) {
                if ($currentValue[$i] > $max) {
                    $max = $currentValue[$i];
                    $largestKey = $i;
                }
            }

            $currentValue = array(
                'key' => $largestKey,
                'value' => $max
            );
        }

        /* Transform and sort them again
         */
        $final = array();

        foreach ($allValues as $key => $struct) {

        }

        ksort($final);



        /* Set up a list of lists
         */
//        for ($j = 0; $j < count($allResults); $j++) {
//            $currentResult = $allResults[$j];
//
//            if ($allValues = unserialize($currentResult['response'])) {
//                $scoreboard[$j] = $allValues;
//            }
//        }


//        /* Summate each position
//         */
//        $position = array();
//
//        $numPositions = count($scoreboard[0]);
//
//        foreach ($scoreboard as $list) {
//            for ($i = 0; $i < $numPositions; $i++) {
//                $position[$i][$list[$i]]++;
//            }
//        }
//
//        new \Alaress_ThreeProbe($scoreboard, $position, null);

        $page->parse('graph_block', $componentHandle);
    }
} 