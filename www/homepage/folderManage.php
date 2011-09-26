<?php
$this->setBlock($componentHandle, "manage_page", "manage_page_block");
$this->setBlock($componentHandle, "stats_data", "stats_data_block");
$this->setBlock($componentHandle, "addmylinks", "addmylinks_block");

//My links button
require_once($_SERVER['DOCUMENT_ROOT'] . '/../library/my-links.php');
$mylinkHandler = new MyLinks();
$url = 'http://'.Cfg::get('site_hostname') . '.' . Cfg::get('site_domain').'/homepage/'.$categoryID;

if($mylinkHandler->linkExists($_SESSION['id'], $url)) {
    $this->setVar('addmylinks_block','');
}
else {
    $this->setVar('HOMEPAGE_URL', $url);
    $this->parse('addmylinks_block','addmylinks');
}

if($permission >= FULL_ACCESS) //is owner of area
    $this->parse("manage_page_block", "manage_page");
else
    $this->setVar("manage_page_block", "");

if($permission >= WRITE_ACCESS) //has write access
    $this->parse("stats_data_block", "stats_data");
else
    $this->setVar("stats_data_block", "");

/* User subscription to groups.
 */
$this->setBlock($componentHandle, 'subscribed', 'subscribed_block');
$this->setBlock($componentHandle, 'subscribedModerated', 'subscribedModerated_block');
$this->setBlock($componentHandle, 'unsubscribed', 'unsubscribed_block');

$this->setVar('subscribed_block', '');
$this->setVar('subscribedModerated_block', '');
$this->setVar('unsubscribed_block', '');

/* If you are subscribed but are awaiting authorisation
 */
$type = $groupHandler->involvedInGroup($_SESSION['id'], $categoryID);

if ($type == DENY_ACCESS && $type !== NULL) {
	$this->parse('subscribedModerated_block', 'subscribedModerated');
}

/* If you are subscribed with something greater than DENY_ACCESS
 */
else if ($type == READ_ACCESS || $type == WRITE_ACCESS) {
	$this->parse('subscribed_block', 'subscribed');
}
else if($type == FULL_ACCESS) {
    
}

/* You aren't subscribed to the group yet.
 * If you can subscribe to the group, present option.
 * Otherwise do nothing
 */
else if ($groupHandler->canSubscribe($categoryID)) {

	$this->parse('unsubscribed_block', 'unsubscribed');
}

?>