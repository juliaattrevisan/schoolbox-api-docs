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

$dailyQuoteConfigMapper = new \DailyQuote\ConfigMapper();
$lastSelected = $dailyQuoteConfigMapper->findByColumn('name', 'last_selected')->first();

$now = new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U');

if ($lastSelected != $now->getDatabase()) {
	$dailyQuoteMapper = new \DailyQuote\QuoteMapper();
	$dailyQuote = $dailyQuoteMapper->findRandomQuote();

	new \Alaress_Probe($dailyQuote);
}
