<?php
//link output
$this->setBlock($componentHandle, "link", "link_block");

require_once '../library/link.php';
$linkHandler = new Link;
$linkList = $linkHandler->getAllInCategory($categoryID, $config);

if (is_array($linkList)) {
	foreach ($linkList as $link) {

		$output = array(
				'LINK_ID'			=> $link['id'],
				'LINK_NAME'			=> scrubTEXT($link['description']),
				'LINK_URL'			=> isLocalURL($link['url']) ? $link['url'] : '/redir.php?url='.urlencode(urlencode($link['url'])),
				'LINK_POSTED_BY'	=> formatName($link),
				'LINK_POSTED_BY_ID'	=> $link['owner'],
				'LINK_POSTED_DATE'	=> outputDate(DATE_short_format, $link['submitted']),
				'LINK_COMMENT'		=> nl2br(scrubTEXT($link['comment'])),
				'ON_OFF'			=> ++$j%2? '' : 'dark',
				);
		$this->setVar($output);
		
		$this->parse("link_block", "link", true);
		

	}
} else
	$this->setVar("link_block", "");

//setup block for submission button
$this->setBlock($componentHandle, "submit_link", "submit_link_block");
if ($permission < WRITE_ACCESS)		
	$this->setVar("submit_link_block", "");
else
	$this->parse("submit_link_block", "submit_link");	



?>