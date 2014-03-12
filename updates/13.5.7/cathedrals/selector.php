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
$dailyQuote = $dailyQuoteMapper->findRandomQuote();

new \Alaress_Probe($dailyQuote);