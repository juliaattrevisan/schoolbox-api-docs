<?php
/**
 * Page for creating a task
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\Resource\Category;
use Schoolbox\Resource\CategoryMapper;

/** @var SitePage $this */

// Wrangle arguments
$category_id = $this->arg[0];
$task_type   = 'task';
if (isset($this->arg[1])) {
    switch ($this->arg[1]) {
        case 'link':
            $task_type = 'link';
            break;
        default:
            break;
    }
}

/** @var Category $category */
$category = CategoryMapper::getInstance()->findById($category_id);

// TODO Check category permissions - user needs WRITE_ACCESS to category

$this->setFile(TEMPLATE_content, "task/create/$task_type.html");
$this->setVar(
    array(
        'TASK_CATEGORY' => $category_id,
        'TASK_TYPE'     => $task_type,
    )
);
