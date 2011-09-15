<?php
require_once SITE_root.'/library/poll.php';

$this->setBlock($componentHandle, "poll_vote", "poll_vote_block");
$this->setBlock("poll_vote", "poll_options", "poll_options_block");
$this->setBlock($componentHandle, "show_results", "show_results_block");

$this->setBlock($componentHandle, "poll_responses", "poll_responses_block");
$this->setBlock($componentHandle, "response_count", "response_count_block");
$this->setBlock('poll_responses', 'series_separator', 'series_separator_block');


$pollHandler = new Poll();

if ( !empty($config['pollID']) && is_numeric($config['pollID']) ) {
	$poll = $pollHandler->getByID($config['pollID']);

    if ($poll['pollID']) {
        $this->setVar(array(
            'POLL_ID'		=> $poll['pollID'],
            'POLL_TITLE'	=> Scrub::text($poll['title']),
            'POLL_QUESTION'	=> Scrub::text($poll['question']),
        ));

        $pollData = $pollHandler->getData($poll['pollID']);

        if ($pollHandler->hasVoted($poll['pollID']) || !$pollHandler->open($poll['pollID']) || $this->arg[1] == 'pollResults')  {
            $this->setVar('POLL_HEIGHT', count($pollData) * 30 + 70);
            $this->setVar('poll_vote_block', '');
            $this->setVar('poll_answers_block', '');
			$this->setVar('poll_responses_block', '');
			$this->setVar('show_results_block', '');
			$this->setVar('response_count', count($pollData));

            if (is_array($pollData)) {
                $i = 0;
				$a = 'A';
                foreach ($pollData as $data) {
                    $this->setVar(array(
                        'POLL_OPTION_INDEX'	=> $i,
                        'POLL_OPTION'		=> Scrub::text($data['option']),
                        'POLL_ON_OFF'		=> $i % 2 ? 'polloff':'',
						'OPTION'		=> htmlspecialchars($data['option']),
						'OPTION_SHORT'	=> $a++,
						'VALUE'			=> htmlspecialchars($data['result']),
                    ));

					if ($i < (count($pollData))-1) {
						$this->parse('series_separator_block', 'series_separator');
					}
					else {
						$this->setVar('series_separator_block', '');
					}
					$this->parse("poll_responses_block", "poll_responses", true);

                    $i++;

                }
            }

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

				/* Show results button
				 */


				if ($pollHandler->hasResults($poll['pollID'])) {
					$this->parse('show_results_block', 'show_results');
				}

            }

            $this->setVar('poll_chart_block', '');
            $this->parse('poll_vote_block', 'poll_vote');
        }
    }
    else {
		$this->setVar('show_results_block', '');
        $this->setVar(array(
            'POLL_ID'		=> '',
            'POLL_TITLE'	=> 'Poll',
            'POLL_QUESTION'	=> '',
        ));

        $this->setVar("poll_chart_block", '');
        $this->setVar("poll_vote_block", "");
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
	
}


/* Poll selection
 */
$this->setBlock("config", "select", "select_block".$component['homepageComponentLocationID']);

$pollList = $pollHandler->getAllByCategory($categoryID);

if (is_array($pollList)) {
	foreach ($pollList as $poll) {
		$this->setVar(array(
			'COMPONENT_CONFIG_SELECT_NAME' 		=> Scrub::text($poll['title']),
			'COMPONENT_CONFIG_SELECT_VALUE' 	=> $poll['pollID'],
			'COMPONENT_CONFIG_SELECT_SELECTED'	=> $poll['pollID'] == $current['pollID'] ? 'selected="selected"' : '',
		));

		$this->parse("select_block".$component['homepageComponentLocationID'], "select", true);
	}
}

/* Admin controls
 */
$this->setBlock($componentHandle, "modify_poll", "modify_poll_block");

if ($permission < WRITE_ACCESS)	{
	$this->setVar("modify_poll_block", "");
}
else {
	$this->parse("modify_poll_block", "modify_poll");
}
?>