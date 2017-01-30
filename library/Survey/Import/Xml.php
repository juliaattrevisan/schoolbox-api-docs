<?php
/**
 * Survey_Import_Xml
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Survey_Import_Xml extends Evaluation_Import_Xml {

    /**
     * Creates the evaluation (without sections, questions or solutions)
     *
     * @param SimpleXMLElement $xml
     * @return int                   The newly assigned assess_id
     */
    protected function createEvaluation(SimpleXMLElement $xml)
    {
        $dataArr = array(
            'category_id'        => $this->getCategoryId(),
            'user_id'            => $_SESSION['id'],
            'type'               => 11,
            'title'              => (string)$xml->title,
            'description'        => $this->parseRichTextNode($xml, 'description'),
            'completion_message' => $this->parseRichTextNode($xml, 'completionmessage'),
            'start'              => $xml->start,
            'finish'             => $xml->finish,
            'max_attempts'       => $this->getMaxAttempts($xml),
            'availability'       => $xml->availability,
            'anonymous_result'   => $xml->anonymousresult,
            'notify_author'      => $xml->notifyauthor,
            'created_at'         => 'NOW()',
        );

        return $this->getAssessHandler()->create($dataArr);
    }
}
