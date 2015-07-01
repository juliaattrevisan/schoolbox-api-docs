<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

if(!isset($this)) {
    require_once dirname(__FILE__) . '/../include/Bootstrap.php';
    SitePage::localRedir('/adminv2/resultsets');
}

$groupHandler = new Group();
if(!$groupHandler->isSuperuser()) {
    error(101);
}

$this->setFile(TEMPLATE_content, 'adminv2/rubric-results.html');
$this->setBlock(TEMPLATE_content, 'list', 'list_block');
$this->setBlock('list', 'list_item', 'list_item_block');

// include the admin navigation
$nav = new AdminV2_Nav();
$nav->parse($this, 'rubric-results');

$stmt = Database::getInstance()->prepare("
    SELECT * FROM rubric_result ORDER BY resultset_id ASC, `value` ASC
");
$stmt->execute();

$sets = array();
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $sets[$row['resultset_id']][$row['value']] = $row['label'];
}

$setNumber = 0;
if(count($sets)) {
    foreach($sets as $setID => $items) {
        $this->setVar('list_item_block', '');
        $this->setVar('RR_SET_NUMBER', $setNumber++);
        $resultNumber = 0;
        foreach($items as $value => $label) {
            $this->setVar(array(
                'RR_VALUE' => $value,
                'RR_LABEL' => $label,
                'RR_SANE_LABEL' => htmlentities($label),
                'RR_RESULT_NUMBER' => $resultNumber++,
                'ON_OFF' => $setID % 2 ? '' : 'dark'
            ));
            $this->parse('list_item_block', 'list_item', true);
            $this->setVar('RR_VALUE', ++$value);
        }
        $this->parse('list_block', 'list', true);
        $this->setVar('ON_OFF', ++$setID % 2 ? '' : 'dark');
    }
}
else {
    $this->setVar(array(
        'list_block' => '',
        'ON_OFF' => ''
    ));
}
// Last set: set set number
$this->setVar('RR_SET_NUMBER', $setNumber);
