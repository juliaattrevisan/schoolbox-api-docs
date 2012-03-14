<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once('../include/Bootstrap.php');

$assess = new Evaluation_Prototype_Tree(100, Evaluation_Prototype_Parameters::SUMMARY, new Evaluation_Prototype_Response_Factory(), $_SESSION['id']);
//$assess->getParameters()->setAttemptIdFilter(169);
//$assess->getParameters()->setQuestionIdFilter(388);
$assess->growTree();
$assess->dumpTree();

