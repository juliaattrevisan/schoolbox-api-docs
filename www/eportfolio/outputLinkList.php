<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/eportfolio-link.php';

$this->setBlock($componentHandle, 'link', 'link_block');
$this->setBlock('link', 'modify_link', 'modify_link_block');

$userHandler = new User();
$linkHandler = new ePortfolioLink;
$linkList = $linkHandler->getAllInUser($userID, $config);

if (is_array($linkList)) {
	foreach($linkList as $link) {
		$url = new Url($link['url']);

		if ($url->isLocalUrl()) {
			$linkUrl = $url->getRelativeURL();
		}
		else {
			$linkUrl = $url->getBase64EncodedRedirect();
		}

		$postedDate = new Alaress_Date_Format($link['submitted'], 'U');

		$this->setVar(array(
            'LINK_ID' => $link['id'],
            'LINK_NAME' => Scrub::text($link['description']),
            'LINK_URL' => $linkUrl,
            'LINK_POSTED_BY' => $userHandler->formatName($link),
            'LINK_POSTED_BY_ID' => $link['owner'],
            'LINK_POSTED_DATE' => $postedDate->getShort(),
            'LINK_COMMENT' => nl2br(Scrub::text($link['comment'])),
            'ON_OFF' => ++$j%2? '' : 'dark',
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
	$this->parse("submit_link_block", "submit_link");
}
else {
	$this->setVar("submit_link_block", "");

}
?>