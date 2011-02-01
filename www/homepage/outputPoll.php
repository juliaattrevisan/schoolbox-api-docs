<?php

$c = array('select' => 0);
//print serialize($c);

$this->setBlock($componentHandle, "poll_vote", "poll_vote_block");
$this->setBlock("poll_vote", "poll_options", "poll_options_block");
$this->setBlock($componentHandle, "show_results", "show_results_block");
$this->setBlock($componentHandle, "poll_chart", "poll_chart_block");
$this->setBlock('poll_chart', "poll_answers", "poll_answers_block");
$this->setJavascript('/javascript/fusion.js');

require_once '../library/poll.php';
$pollHandler = new Poll;

if (!empty($config['pollID']) && is_numeric($config['pollID']))
	$poll = $pollHandler->getByID($config['pollID']);

if (!isset($poll['pollID']))
	$poll = $pollHandler->getCurrentPoll($categoryID);

if ($poll['pollID']) {

	$output = array('POLL_ID'		=> $poll['pollID'],
					'POLL_TITLE'	=> scrubText($poll['title']),
					'POLL_QUESTION'	=> scrubText($poll['question']),
					);
	$this->setVar($output);
	
	$pollData = $pollHandler->getData($poll['pollID']);
	if ($pollHandler->hasVoted($poll['pollID']) || !$pollHandler->open($poll['pollID']) || $this->arg[1] == 'pollResults') {
		
		$this->setVar('POLL_HEIGHT', count($pollData) * 30 + 70);
		$this->setVar('poll_vote_block', '');

        $this->setVar('poll_answers_block', '');
		if (is_array($pollData)) {
			$i = 0;
			foreach ($pollData as $data) {
			
				$output = array('POLL_OPTION_INDEX'	=> $i,
								'POLL_OPTION'		=> scrubText($data['option']),
								'POLL_ON_OFF'		=> $i % 2 ? 'polloff':'',
								);
				$i++;
				$this->setVar($output);
				$this->parse("poll_answers_block", "poll_answers", true);
				
			}
		}		
		
		$this->parse('poll_chart_block', 'poll_chart');
		
	}
	else {
	
		$this->setVar('poll_options_block', '');
		if (is_array($pollData)) {
			$i = 0;
			foreach ($pollData as $data) {
			
				$output = array('POLL_OPTION_INDEX'	=> $i,
								'POLL_OPTION'		=> scrubText($data['option']),
								'POLL_ON_OFF'		=> $i % 2 ? 'polloff':'',
								);
				$i++;
				$this->setVar($output);
				$this->parse('poll_options_block', 'poll_options', true);
				
			}
		}
		
		$this->setVar('poll_chart_block', '');
		$this->parse('poll_vote_block', 'poll_vote');
	}
	
	
} else {
	$output = array('POLL_ID'		=> '',
					'POLL_TITLE'	=> 'Poll',
					'POLL_QUESTION'	=> '',
					);
	$this->setVar($output);
	$this->setVar("poll_chart_block", '');
		
	$this->setVar("poll_vote_block", "");
}

$current = $poll;

/* poll selection */
$this->setBlock("config", "select", "select_block".$component['homepageComponentLocationID']);
$pollList = $pollHandler->getAllByCategory($categoryID);
if (is_array($pollList)) {
	foreach ($pollList as $poll) {
		$output = array('COMPONENT_CONFIG_SELECT_NAME' 		=> scrubText($poll['title']),
						'COMPONENT_CONFIG_SELECT_VALUE' 	=> $poll['pollID'],
						'COMPONENT_CONFIG_SELECT_SELECTED'	=> $poll['pollID'] == $current['pollID'] ? 'selected="selected"' : '',
						);
		$this->setVar($output);
		$this->parse("select_block".$component['homepageComponentLocationID'], "select", true);
	}
}

/* show results */
$this->setVar('show_results_block', '');
if ($pollHandler->hasResults($poll['pollID']))
	$this->parse('show_results_block', 'show_results');

/* admin controls */
$this->setBlock($componentHandle, "modify_poll", "modify_poll_block");
if ($permission < WRITE_ACCESS)		
	$this->setVar("modify_poll_block", "");
else
	$this->parse("modify_poll_block", "modify_poll");	

?>