<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Permission\Access\UserAccess;

class Component_Homepage_PollController extends Component_Homepage_ControllerAbstract
{
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

        /* Need the title injected here
                 */
        $this->parseConfig();

        $pollHandler = new Poll();

        $this->setBlock($templateHandle, 'has_poll', 'has_poll_block');
        $this->setBlock($templateHandle, 'empty_state', 'empty_state_block');

        if ($this->getConfig('poll') && is_numeric($this->getConfig('poll'))) {
            $this->setBlock('has_poll', "poll_question", "poll_question_block");
            $this->setBlock('has_poll', "response_count", "response_count_block");
            $this->setBlock('has_poll', 'new_poll', 'new_poll_block');
            $this->setBlock('poll_question', "poll_vote", "poll_vote_block");
            $this->setBlock('poll_question', "poll_actions", "poll_actions_block");
            $this->setBlock('poll_actions', "show_results", "show_results_block");
            $this->setBlock("poll_vote", "poll_options", "poll_options_block");


            $this->setBlock('poll_question', "poll_chart", "poll_chart_block");
            // $this->setBlock('poll_chart', "poll_responses", "poll_responses_block");
            // $this->setBlock('poll_responses', 'series_separator', 'series_separator_block');
            $this->setVar('poll_responses_block', '');

            $this->setBlock('poll_question', 'owner_exists', 'owner_exists_block');
            $this->setBlock('poll_question', 'owner_deleted', 'owner_deleted_block');
            $this->setBlock('poll_actions', "modify_poll", "modify_poll_block");


            $this->setVar(
                [
                    'empty_state_block'   => '',
                    'poll_question_block' => '',
                    'show_results_block'  => '',
                    'poll_actions_block'  => '',
                ]
            );

            $poll = $pollHandler->getByID($this->getConfig('poll'));

            $created = new Alaress_Date_Format($poll['created'], Alaress_Date_Format::DATABASE_FORMAT);

            try {
                $author = \Schoolbox\UserMapper::getInstance()->findById($poll['created_by']);
            } catch (Deleted $de) {
                $author = $de->getDeletedObject();
            } catch (\Schoolbox\Mapper\Exception $e) {
                $author = \Schoolbox\UserMapper::getInstance()->getUnknownUser();
            }
            /** @var \Schoolbox\User $author **/

            if ($poll['id']) {
                $this->setComponentName(strlen(trim($this->getConfig('componentname'))) ? $this->getConfig('componentname') : $this->getComponentName());
                $this->setVar(
                    [
                        'POLL_ID'           => $poll['id'],
                        'FOLDER_ID'         => $poll['folder_id'],
                        'POLL_QUESTION'     => Scrub::text($poll['question']),
                        'POLL_AUTHOR_ID'    => $author->getId(),
                        'POLL_AUTHOR_THUMB' => "/portrait.php?id={$author->getId()}&size=square64",
                        'POLL_AUTHOR_NAME'  => $author->getFullName(),
                        'POLL_CREATED'      => $created->getRelativeTime(),
                    ]
                );

                // Owner name and optional link
                if ($author->isDeleted()) {
                    $this->setVar('owner_exists_block', '');
                    $this->parse('owner_deleted_block', 'owner_deleted');
                } else {
                    $this->parse('owner_exists_block', 'owner_exists');
                    $this->setVar('owner_deleted_block', '');
                }

                $pollData = $pollHandler->getData($poll['id']);

                if ($pollHandler->hasVoted($poll['id']) || !$pollHandler->open($poll['id']) || ($this->getSitePage()->arg[1] ?? '') == 'pollResults') {
                    /* Show poll as user has voted or the poll has closed to votes */

                    $this->setVar(
                        [
                            'POLL_HEIGHT'     => count($pollData) * 30 + 70,
                            'poll_vote_block' => '',
                            'response_count'  => count($pollData),
                        ]
                    );

                    if (is_array($pollData)) {
                        $i        = 0;
                        $pollJson = [];
                        foreach ($pollData as $data) {
                            $pollJson[] = [
                                'name' => $data['option'],
                                'data' => [intval($data['result'])],
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
                                [
                                    'POLL_OPTION_INDEX' => $i,
                                    'POLL_OPTION'       => Scrub::text($data['option']),
                                    'POLL_ON_OFF'       => $i % 2 ? 'polloff' : '',
                                ]
                            );

                            $this->parse('poll_options_block', 'poll_options', true);

                            $i++;
                        }

                        $this->parse('show_results_block', 'show_results');
                        $this->parse('poll_actions_block', 'poll_actions');
                    }
                    $this->setVar('poll_chart_block', '');
                    $this->parse('poll_vote_block', 'poll_vote');
                }

                if ($this->getContentPermission() < UserAccess::ACCESS_WRITE) {
                    $this->setVar("modify_poll_block", "");
                    $this->setVar('new_poll_block', '');
                } else {
                    $this->parse("modify_poll_block", "modify_poll");
                    $this->parse('new_poll_block', 'new_poll');
                    $this->parse('poll_actions_block', 'poll_actions');
                }

                $this->parse('poll_question_block', 'poll_question');
            } else {
                $this->setVar(
                    [
                        'POLL_ID'           => '',
                        'POLL_TITLE'        => 'Poll',
                        'POLL_QUESTION'     => '',
                        'poll_chart_block'  => '',
                        'poll_vote_block'   => '',
                        'modify_poll_block' => '',
                        'new_poll_block'    => '',
                    ]
                );

                if ($this->getContentPermission() >= UserAccess::ACCESS_WRITE) {
                    $this->parse('new_poll_block', 'new_poll');
                }
            }

            $this->parse('has_poll_block', 'has_poll');

            $current = $poll;
        } elseif ($this->getContentPermission() < UserAccess::ACCESS_WRITE) {
            return false;
        } else {
            $this->setVar(
                [
                    'has_poll_block'    => '',
                    'empty_write_block' => '',
                    'empty_read_block'  => '',
                ]
            );

            $this->parse('empty_state_block', 'empty_state');
        }

        return true;
    }
}
