<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once __DIR__.'/../../../include/Bootstrap.php';

$dailyQuoteMapper = new \DailyQuote\QuoteMapper();
$dailyQuoteConfigMapper = new \DailyQuote\ConfigMapper();

$lastSelectedDate = $dailyQuoteConfigMapper->findByColumn('name', 'last_selected_date')->first();
$lastSelectedIndex = $dailyQuoteConfigMapper->findByColumn('name', 'last_selected_index')->first();

$now = new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U');

/* Time to select a new quote and update the db
 */
if ($lastSelectedDate->getValue() != $now->getDatabaseDate()) {
	$dailyQuote = $dailyQuoteMapper->findRandomQuote();

	/* Remember when the quote was last selected
	 */
	$lastSelectedDate->setValue($now->getDatabaseDate());
	$dailyQuoteConfigMapper->save($lastSelectedDate);

	/* Remember the quote that was last selected
	 */

	$lastSelectedIndex->setValue($dailyQuote->getId());
	$dailyQuoteConfigMapper->save($lastSelectedIndex);
}
/* Reuse a previously selected quote
 */
else {
	$dailyQuote = $dailyQuoteMapper->findById($lastSelectedIndex->getValue());
}

new \Alaress_Probe($dailyQuote->getDescription());

