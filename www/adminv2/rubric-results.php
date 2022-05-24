<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Admin\Navigation\Subnav;

define('RESULTSET_MAXLENGTH', 10);

if (!isset($this)) {
    require_once __DIR__ . '/../../conf/core/require.php';
    $kernel = new \Schoolbox\Application\WebRequest\Kernel();
    SitePage::localRedir('/adminv2/resultsets');
}

if (!$_SESSION['user']->isSuperuser()) {
    error(101, "", 401);
}

$this->setFile(TEMPLATE_content, 'adminv2/rubric-results.html');
$this->setBlock(TEMPLATE_content, 'list', 'list_block');
$this->setBlock('list', 'list_item', 'list_item_block');

// include the admin navigation
(new Subnav($this->getDi()->get('config')))
    ->generatePhplib($this, '/adminv2/rubric-results')
    ->generateBreadcrumbsHtml($this, '/adminv2/rubric-results');

$stmt = Database::getInstance()->prepare("
    SELECT * FROM rubric_result ORDER BY resultset_id ASC, `value` ASC
");
$stmt->execute();

$sets = array();
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $sets[$row['resultset_id']][$row['value']] = $row['label'];
}

$setNumber = 0;
if (count($sets)) {
    foreach ($sets as $setID => $items) {
        $this->setVar('list_item_block', '');
        $this->setVar('RR_SET_NUMBER', $setNumber++);
        $resultNumber = 0;
        foreach ($items as $value => $label) {
            $this->setVar(array(
                'RR_VALUE' => $value,
                'RR_LABEL' => $label,
                'RR_SANE_LABEL' => htmlentities($label),
                'RR_RESULT_NUMBER' => $resultNumber++,
                'RR_MAXLENGTH' => RESULTSET_MAXLENGTH
            ));
            $this->parse('list_item_block', 'list_item', true);
            $this->setVar('RR_VALUE', ++$value);
        }
        $this->parse('list_block', 'list', true);
    }
} else {
    $this->setVar(array(
        'list_block' => '',
    ));
}
// Last set: set set number
$this->setVar('RR_SET_NUMBER', $setNumber);
