<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/Bootstrap.php';

$page = new SitePage;
$wikiHandler = new Wiki;
$groupHandler = new Group(HOMEPAGE_SERVICE);


if (isset($_GET['wikiID'])) {
   // wiki exists
   $wiki = $wikiHandler->getByID($_GET['wikiID']);

   if (!isset($wiki['wikiID'])) {
      error(2);
   }

   if (($permission = $groupHandler->getPermission($wiki['categoryID'])) < READ_ACCESS) {
      error(19);
   }

   // check locked
   if ($wiki['locked'] == 'true' && $permission < WRITE_ACCESS) {
      error(19);
   }

   $wikiContents = $wikiHandler->getByID($_GET['wikiID']);

	$modifiedDate = new Alaress_Date_Format($wikiContents['modified'], 'U');

   $output = array(
      'AUTHOR' => $wikiContents['firstname'] . ' ' . $wikiContents['lastname'],
      'HEADING' => $wikiContents['heading'],
      'BODY' => $wikiContents['body'],
      'LASTMODIFIED' => $modifiedDate->getLong(),
   );

   /* Load Page templating, to keep consistent, rather than dumping raw output.
    * This also allows you to format the exported wiki page.
    */
   $page->setFile(TEMPLATE_body, '/wiki/exportedPage.html');
   $page->setVar($output);

   /* Construct headers so this pops up a Save-As dialog
    */
   $filename = Scrub::text($wikiContents['heading']);

   header('Content-type: text/html');
   header("Content-Disposition: attachment filename=\"".$filename."\"");
   $page->outputPage(true);
}
else {
	error(2);
}

?>