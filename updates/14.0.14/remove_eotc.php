<?php
/**
 * Remove EOTC messages from all schools but STK, this is a custom STK module
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once __DIR__ . '/../../include/Bootstrap.php';

if (Config::get('site_location') != 'stk') {
    Database::getInstance()->query("DELETE mgs_namespace, msg_source FROM msg_namespace,msg_source WHERE id=namespace AND slug = 'eotc'");
}