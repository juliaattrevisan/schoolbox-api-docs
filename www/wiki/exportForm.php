<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/wiki.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/page.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/include/stdfuncs.php';

$wikiHandler = new Wiki;
$groupHandler = new Group(HOMEPAGE_SERVICE);

if (isset($_GET['wikiID'])) {
   // wiki exists
   $wiki = $wikiHandler->getByID($_GET['wikiID']);
   
   if (!isset($wiki['wikiID'])) {
      error(2);
   }

   /* uid is empty unless I use session_start()
    */
   session_start();

   if (($permission = $groupHandler->getPermission($wiki['categoryID'])) < READ_ACCESS) {
      error(19);
   }
   
   // check locked
   if ($wiki['locked'] == 'true' && $permission < WRITE_ACCESS) {
      error(19);
   }

   $wikiContents = $wikiHandler->getByID($_GET['wikiID']);

   $output = array(
      'AUTHOR' => $wikiContents['firstname'] . ' ' . $wikiContents['lastname'],
      'HEADING' => $wikiContents['heading'],
      'BODY' => $wikiContents['body'],
      'LASTMODIFIED' => outputDate('l d F, Y, H:i:sa', $wikiContents['modified']),
   );

   /* Load Page templating, to keep consistent, rather than dumping raw output.
    * This also allows you to format the exported wiki page.
    */
   $page = new Page;
   $page->setFile(TEMPLATE_body, 'core/wiki/exportedPage.html');
   $page->setVar($output);
   $page->parse('finaloutput', TEMPLATE_body);

   /* Construct headers so this pops up a Save-As dialog
    */
   $filename = scrubText($wikiContents['heading']);

   header('Content-type: text/html');
   header("Content-Disposition: attachment filename=\"".$filename."\"");
   $page->outputPage();
}
else {
	error(2);
}

?>