<?php

use Schoolbox\Application\WebRequest\Kernel;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User\Formatter\Factory;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new Kernel();

/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();
$wikiHandler  = new Wiki();
$groupHandler = new Group(UserAccess::SERVICE_HOMEPAGE);

if (isset($_GET['wikiID'])) {
    // wiki exists
    $wiki = $wikiHandler->getByID($_GET['wikiID']);

    if (!isset($wiki['wikiID'])) {
        error(2);
    }

    if (($permission = $groupHandler->getPermission($wiki['categoryID'])) < UserAccess::ACCESS_READ) {
        error(19);
    }

    $wikiContents = $wikiHandler->getByID($_GET['wikiID']);

    /* Enforce absolute paths in relative links when exported
     */
    $hostURL = SitePage::conType() . ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? '');

    $matches = [];
    $body    = $wikiContents['body'];

    preg_match_all("/<a href=['\"](\/[^'\"]+)['\"][^>]*>/", $body, $matches);

    if (count($matches)) {
        for ($i = 0; $i < count($matches[1]); $i++) {
            $newLink = '<a href="' . $hostURL . $matches[1][$i] . '">';

            $body = str_replace($matches[0][$i], $newLink, $body);
        }
    }

    /* Date formatting
     */
    $modifiedDate = new Alaress_Date_Format($wikiContents['modified'], 'U');

    $output = [
        'AUTHOR'       => (new Factory())->buildFromNothing()->formatArray($wikiContents),
        'HEADING'      => Scrub::text($wikiContents['heading']),
        'BODY'         => $body,
        'LASTMODIFIED' => $modifiedDate->getLong(),
    ];

    /* Load Page templating, to keep consistent, rather than dumping raw output.
     * This also allows you to format the exported wiki page.
     */
    $page->setFile(TEMPLATE_content, '/wiki/exportedPage.html');
    $page->setVar($output);

    /* Construct headers so this pops up a Save-As dialog
     */
    $filename = Scrub::text($wikiContents['heading']) . '.html';

    header('Content-type: text/html');
    header("Content-Disposition: attachment; filename=\"$filename\"");
    $page->outputPage(true);
} else {
    error(2);
}
