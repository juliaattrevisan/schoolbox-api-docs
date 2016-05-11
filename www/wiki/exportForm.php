<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/Bootstrap.php';

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

    $wikiContents = $wikiHandler->getByID($_GET['wikiID']);

    /* Enforce absolute paths in relative links when exported
     */
    $hostURL = SitePage::conType() . $_SERVER['HTTP_HOST'];

    $matches = array();
    $body = $wikiContents['body'];

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

    $output = array(
        'AUTHOR' => $wikiContents['firstname'] . ' ' . $wikiContents['lastname'],
        'HEADING' => Scrub::text($wikiContents['heading']),
        'BODY' => $body,
        'LASTMODIFIED' => $modifiedDate->getLong(),
    );

    /* Load Page templating, to keep consistent, rather than dumping raw output.
     * This also allows you to format the exported wiki page.
     */
    $page->setFile(TEMPLATE_body, '/wiki/exportedPage.html');
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

