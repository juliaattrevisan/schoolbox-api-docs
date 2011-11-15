<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/my-links.php';
require_once($_SERVER['DOCUMENT_ROOT'] . '/../library/Eportfolio/Link.php');

$this->setFile(TEMPLATE_content, 'cms/myLinks.html');
$this->setBlock(TEMPLATE_content, 'myLink', 'myLink_block');
$this->setBlock('myLink', 'myLink_exists', 'myLink_exists_block');
$this->setBlock('myLink', 'myLink_share', 'myLink_share_block');

$myLinksHandler = new MyLinks();
$ePortfolioLinkHandler = new Eportfolio_Link();

$allLinks = $myLinksHandler->getAllByField('owner', $_SESSION['id']);

if (is_array($allLinks)) {
	foreach($allLinks as $currentLink) {
		$this->setVar(array(
			'MYLINK_URL' => $currentLink['url'],
			'MYLINK_DESCRIPTION' => $currentLink['description'],
			'MYLINK_ID' => $currentLink['id'],
		));

		/* Indicate whether link is already shared on ePortfolio
		 */
		$exists = $ePortfolioLinkHandler->getAllByFields(array(
			'owner' => $_SESSION['id'],
			'url' => $currentLink['url'],
		));

		if ($exists) {
			$this->parse('myLink_exists_block', 'myLink_exists');
			$this->setVar('myLink_share_block', '');
		}
		else {
			$this->setVar('myLink_exists_block', '');
			$this->parse('myLink_share_block', 'myLink_share');
		}

		$this->parse('myLink_block', 'myLink', true);

		$this->setVar('myLink_exists_block', '');
		$this->setVar('myLink_share_block', '');
	}
}
else {
	$this->setVar('myLink_block', '');
}
?>
