<?php
require_once '../library/eportfolio-link.php';

$this->setBlock($componentHandle, 'link', 'link_block');
$this->setBlock('link', 'modify_link', 'modify_link_block');

$linkHandler = new ePortfolioLink;
$linkList = $linkHandler->getAllInUser($userID, $config);

if (is_array($linkList)) {
	foreach($linkList as $link) {
		$this->setVar(array(
            'LINK_ID'			=> $link['id'],
            'LINK_NAME'			=> scrubTEXT($link['description']),
            'LINK_URL'			=> isLocalURL($link['url']) ? relativeURL($link['url']) : '/redir.php?url='.base64_encode($link['url']),
            'LINK_POSTED_BY'	=> formatName($link),
            'LINK_POSTED_BY_ID'	=> $link['owner'],
            'LINK_POSTED_DATE'	=> outputDate(Cfg::get('date_short'), $link['submitted']),
            'LINK_COMMENT'		=> nl2br(scrubTEXT($link['comment'])),
            'ON_OFF'			=> ++$j%2? '' : 'dark',
        ));
		
		if (!$eportfolioHandler->canEdit($userID)) {
			$this->setVar('modify_link_block', '');
        }
		else {
			$this->parse('modify_link_block', 'modify_link');
        }
		
		$this->parse("link_block", "link", true);
	}
}
else {
	$this->setVar("link_block", "");
}

/* Setup block for submission button
 */
$this->setBlock($componentHandle, "submit_link", "submit_link_block");

if ($eportfolioHandler->canEdit($userID)) {
	$this->setVar("submit_link_block", "");
}
else {
	$this->parse("submit_link_block", "submit_link");
}
?>