<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_PollController extends Component_Homepage_ControllerAbstract
{

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute()
    {

        // TODO: Implement execute() method.

    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'poll')) {
            return false;
        }

        $this->setBlock($templateHandle, "poll_question", "poll_question_block");
        $this->setBlock('poll_question', "poll_vote", "poll_vote_block");
        $this->setBlock('poll_question', "show_results", "show_results_block");
        $this->setBlock("poll_vote", "poll_options", "poll_options_block");

        $this->setBlock($templateHandle, "response_count", "response_count_block");
        $this->setBlock($templateHandle, 'new_poll', 'new_poll_block');

        $this->setBlock('poll_question', "poll_chart", "poll_chart_block");
//        $this->setBlock('poll_chart', "poll_responses", "poll_responses_block");
//        $this->setBlock('poll_responses', 'series_separator', 'series_separator_block');
        $this->setVar('poll_responses_block', '');

        $this->setBlock('poll_question', "modify_poll", "modify_poll_block");

        $pollHandler = new Poll();

        $this->setVar('poll_question_block', '');
        $this->setVar('show_results_block', '');

        if ($this->getConfig('poll') && is_numeric($this->getConfig('poll'))) {
            $poll = $pollHandler->getByID($this->getConfig('poll'));

            $created = new Alaress_Date_Format;
            $created->setFromFormat($poll['created'], Alaress_Date_Format::DATABASE_FORMAT);

            try {
                $author = \Schoolbox\UserMapper::getInstance()->findById($poll['created_by']);
            } catch (\Schoolbox\Mapper\Exception $e) {
                $author = \Schoolbox\UserMapper::getInstance()->getUnknownUser();
            }
            if ($poll['pollID']) {
                $this->setComponentName(strlen(trim($this->getConfig('componentname'))) ? $this->getConfig('componentname') : $this->getComponentName());

                $this->setVar(
                    array(
                        'POLL_ID'           => $poll['pollID'],
                        'POLL_QUESTION'     => Scrub::text($poll['question']),
                        'POLL_AUTHOR_ID'    => $author->getId(),
                        'POLL_AUTHOR_THUMB' => "/portrait.php?userId={$author->getExternalId()}&size=square64",
                        'POLL_AUTHOR_NAME'  => $author->getFullName(),
                        'POLL_CREATED'      => $created->getRelativeTime(),
                    )
                );

                $pollData = $pollHandler->getData($poll['pollID']);

                if ($pollHandler->hasVoted($poll['pollID']) || !$pollHandler->open($poll['pollID']) || $this->sitepage->arg[1] == 'pollResults') {
                    /* Show poll as user has voted or the poll has closed to votes */

                    $this->setVar(
                        array(
                            'POLL_HEIGHT'     => count($pollData) * 30 + 70,
                            'poll_vote_block' => '',
                            'response_count'  => count($pollData)
                        )
                    );

                    if (is_array($pollData)) {
                        $i = 0;
                        $pollJson = [];
                        foreach ($pollData as $data) {
                            $pollJson[] = [
                                'name' => $data['option'],
                                'data'  => [intval($data['result'])],
                            ];
                            $i++;
                        }
                        $this->setVar('POLL_DATA', json_encode($pollJson));
                    }
                    $this->parse('poll_chart_block', 'poll_chart');
                } else {
                    /* User has not voted yet */
                    $this->setVar('poll_options_block', '');

                    if (is_array($pollData)) {
                        $i = 0;

                        foreach ($pollData as $data) {
                            $this->setVar(
                                array(
                                    'POLL_OPTION_INDEX' => $i,
                                    'POLL_OPTION'       => Scrub::text($data['option']),
                                    'POLL_ON_OFF'       => $i % 2 ? 'polloff' : '',
                                )
                            );

                            $this->parse('poll_options_block', 'poll_options', true);

                            $i++;
                        }

                        $this->parse('show_results_block', 'show_results');

                    }
                    $this->setVar('poll_chart_block', '');
                    $this->parse('poll_vote_block', 'poll_vote');
                }

                if ($this->permission < WRITE_ACCESS) {
                    $this->setVar("modify_poll_block", "");
                    $this->setVar('new_poll_block', '');
                } else {
                    $this->parse("modify_poll_block", "modify_poll");
                    $this->parse('new_poll_block', 'new_poll');
                }

                $this->parse('poll_question_block', 'poll_question');
            }
            else {
                $this->setVar(array(
                    'POLL_ID'		=> '',
                    'POLL_TITLE'	=> 'Poll',
                    'POLL_QUESTION'	=> '',
                ));

                $this->setVar('poll_chart_block', '');
                $this->setVar('poll_vote_block', '');
                $this->setVar('modify_poll_block', '');

                if ($this->permission < WRITE_ACCESS) {
                    $this->setVar('new_poll_block', '');
                } else {
                    $this->parse('new_poll_block', 'new_poll');
                }

            }

            $current = $poll;
        } else {
            $this->setVar(
                array(
                    'POLL_ID'       => '',
                    'POLL_TITLE'    => 'Poll',
                    'POLL_QUESTION' => '',
                )
            );

            $this->setVar("poll_chart_block", '');
            $this->setVar("poll_vote_block", "");
            $this->setVar('show_results_block', '');
            $this->setVar('modify_poll_block', '');

            if ($this->permission < WRITE_ACCESS) {
                $this->setVar('new_poll_block', '');
            } else {
                $this->parse('new_poll_block', 'new_poll');
            }
        }

        /* Need the title injected here
         */
        $this->parseConfig();

        return true;
    }
}
