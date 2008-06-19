<?php


$this->setBlock($componentHandle, "poll_vote", "poll_vote_block");
$this->setBlock("poll_vote", "poll_options", "poll_options_block");
$this->setBlock($componentHandle, "poll_chart", "poll_chart_block");


require_once '../library/poll.php';
$pollHandler = new Poll;

$poll = $pollHandler->getCurrentPoll($categoryID);
if ($poll['pollID']) {

	$output = array('POLL_ID'		=> $poll['pollID'],
					'POLL_TITLE'	=> scrubText($poll['title']),
					'POLL_QUESTION'	=> scrubText($poll['question']),
					);
	$this->setVar($output);
	

	if ($pollHandler->hasVoted($poll['pollID'])) {
	
		$this->setVar("poll_vote_block", "");
		$this->parse("poll_chart_block", "poll_chart");
	}
	else {
	
		$pollData = $pollHandler->getData($poll['pollID']);
		if (is_array($pollData)) {
			$i = 0;
			foreach ($pollData as $data) {
			
				$output = array('POLL_OPTION_INDEX'	=> $i,
								'POLL_OPTION'		=> scrubText($data['option']),
								);
				$i++;
				$this->setVar($output);
				$this->parse("poll_options_block", "poll_options", true);
				
			}
		}
		
		$this->setVar("poll_chart_block", "");
		$this->parse("poll_vote_block", "poll_vote");
	}
} else {
	$output = array('POLL_ID'		=> '',
					'POLL_TITLE'	=> 'Poll',
					'POLL_QUESTION'	=> '',
					);
	$this->setVar($output);
	
	$this->setVar("poll_chart_block", '<a href="/poll/modify.php?categoryID='.$categoryID.'">Create</a>');
	$this->setVar("poll_vote_block", "");
}
?>