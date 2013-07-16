<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_PollController extends Component_Homepage_ControllerAbstract {

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute() {

        // TODO: Implement execute() method.

    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle) {
        if(!parent::loadTemplate($templateHandle, 'poll')) {
            return false;
        }
        $this->parseConfig();

        $this->setBlock($templateHandle, "poll_vote", "poll_vote_block");
        $this->setBlock($templateHandle, "show_results", "show_results_block");
        $this->setBlock("poll_vote", "poll_options", "poll_options_block");

        $this->setBlock($templateHandle, "poll_responses", "poll_responses_block");
        $this->setBlock($templateHandle, "response_count", "response_count_block");
        $this->setBlock('poll_responses', 'series_separator', 'series_separator_block');
        $this->setBlock($templateHandle, 'new_poll', 'new_poll_block');

        $this->setBlock($templateHandle, "poll_chart", "poll_chart_block");
        $this->setVar('poll_responses_block', '');

        $this->setBlock($templateHandle, "modify_poll", "modify_poll_block");

        $pollHandler = new Poll();

        if ( $this->getConfig('poll') && is_numeric($this->getConfig('poll')) ) {
            $poll = $pollHandler->getByID($this->getConfig('poll'));

            if ($poll['pollID']) {
                $this->setVar(array(
                    'POLL_ID'		=> $poll['pollID'],
                    'POLL_TITLE'	=> Scrub::text($poll['title']),
                    'POLL_QUESTION'	=> Scrub::text($poll['question']),
                ));

                $pollData = $pollHandler->getData($poll['pollID']);

                if ($pollHandler->hasVoted($poll['pollID']) || !$pollHandler->open($poll['pollID']))  {

                    $this->setVar(array(
                        'POLL_HEIGHT'        => count($pollData) * 30 + 70,
                        'poll_vote_block'    => '',
                        'show_results_block' => '',
                        'response_count'     => count($pollData)
                    ));

                    if (is_array($pollData)) {
                        $i = 0;
                        $a = 'A';

                        foreach ($pollData as $data) {
                            $this->setVar(array(
                                'POLL_OPTION_INDEX'	=> $i,
                                'POLL_OPTION'		=> Scrub::text($data['option']),
                                'POLL_ON_OFF'		=> $i % 2 ? 'polloff':'',
                                'OPTION'		    => htmlspecialchars($data['option']),
                                'OPTION_SHORT'	    => $a++,
                                'VALUE'			    => htmlspecialchars($data['result']),
                            ));

                            if ($i < (count($pollData)) - 1) {
                                $this->parse('series_separator_block', 'series_separator');
                            }
                            else {
                                $this->setVar('series_separator_block', '');
                            }
                            $this->parse("poll_responses_block", "poll_responses", true);
                            $i++;
                        }
                    }
                    $this->parse('poll_chart_block', 'poll_chart');
                }
                else {
                    $this->setVar('poll_options_block', '');

                    if (is_array($pollData)) {
                        $i = 0;

                        foreach ($pollData as $data) {
                            $this->setVar(array(
                                'POLL_OPTION_INDEX'	=> $i,
                                'POLL_OPTION'		=> Scrub::text($data['option']),
                                'POLL_ON_OFF'		=> $i % 2 ? 'polloff':'',
                            ));

                            $this->parse('poll_options_block', 'poll_options', true);

                            $i++;
                        }

                        if ($pollHandler->hasResults($poll['pollID'])) {
                            $this->parse('show_results_block', 'show_results');
                        }

                    }
                    $this->setVar('poll_chart_block', '');
                    $this->setVar('show_results_block', '');
                    $this->parse('poll_vote_block', 'poll_vote');
                }

                if ($this->permission < WRITE_ACCESS ) {
                    $this->setVar("modify_poll_block", "");
                    $this->setVar('new_poll_block', '');
                }
                else {
                    $this->parse("modify_poll_block", "modify_poll");
                    $this->parse('new_poll_block', 'new_poll');
                }

            }
            else {
                $this->setVar('show_results_block', '');
                $this->setVar(array(
                    'POLL_ID'		=> '',
                    'POLL_TITLE'	=> 'Poll',
                    'POLL_QUESTION'	=> '',
                ));

                $this->setVar('poll_chart_block', '');
                $this->setVar('poll_vote_block', '');
                $this->setVar('modify_poll_block', '');

                if ($this->permission < WRITE_ACCESS ) {
                    $this->setVar('new_poll_block', '');
                }
                else {
                    $this->parse('new_poll_block', 'new_poll');
                }

            }

            $current = $poll;
        }
        else {
            $this->setVar(array(
                'POLL_ID'		=> '',
                'POLL_TITLE'	=> 'Poll',
                'POLL_QUESTION'	=> '',
            ));

            $this->setVar("poll_chart_block", '');
            $this->setVar("poll_vote_block", "");
            $this->setVar('show_results_block', '');
            $this->setVar('modify_poll_block', '');

            if ($this->permission < WRITE_ACCESS ) {
                $this->setVar('new_poll_block', '');
            }
            else {
                $this->parse('new_poll_block', 'new_poll');
            }

        }

        return true;
    }

}
