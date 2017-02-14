<?php

/**
 * Survey_Export_Xml
 *
 * Export an entire survey (including sections, questions + solutions) into xml format
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Survey_Export_Xml extends Evaluation_Export_Xml
{

    /**
     * Renders the survey-specific data
     *
     * @param mixed[] $assess
     */
    protected function renderEvaluation(array $assess)
    {
        // While 'title' and 'description' are common to quiz + survey, their authoritative sources differ.
        $this->getPage()->setVar([
            'ASSESS_TITLE'            => htmlspecialchars($assess['title'], ENT_QUOTES),
            'ASSESS_DESCRIPTION'      => Scrub::HTML($assess['description']),
            'ASSESS_START'            => $assess['start'],
            'ASSESS_FINISH'           => $assess['finish'],
            'ASSESS_ANONYMOUS_RESULT' => $assess['anonymous_result'],
            'ASSESS_NOTIFY_AUTHOR'    => $assess['notify_author'],
        ]);
        parent::renderEvaluation($assess);
    }

    protected function getEvaluationType()
    {
        return 'survey';
    }

    protected function getTemplateFile()
    {
        return 'survey/exportToMoodle.xml';
    }
}
