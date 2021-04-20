<?php
/**
 * Created by
 * User: jleckie
 * Date: 20/06/11
 * Time: 3:52 PM
 *
 * File implements authentication protocol as defined by eBook Library
 *
 *
 * The forwarding URL could look something like this (using MD5):
 * www.libraryid.eblib.com.au/EBLWeb/patron/?target=patron&extendedid=P_204038_0&userid=43849867&tstamp=1106320584&id=4C4CFE5961E5C56610B821D071737E8D
 * Where the 'id' parameter is generated as follows:
 * User Id + Timestamp + Shared Secret hashed.
 */


require_once __DIR__ . '/../../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();

define('EBL_LIBRARY_ID', 'shore');
define('EBL_SHARED_SECRET', 'bG45902501c3');

$userID = $_SESSION['username'];
$time = time();

$secret = sha1($userID.$time.EBL_SHARED_SECRET);


header('Location: ' .$_GET['url'].'&userid='.$userID.'&tstamp='.$time.'&id='.$secret);
