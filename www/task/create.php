<?php
/**
 * Page for creating a task
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

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

// Shitty permission handling
$group = new Group();
if ($group->getPermission($category_id) < WRITE_ACCESS) {
    error(6);
}

$this->setFile(TEMPLATE_content, "task/create/$task_type.html");
$this->setVar(
    array(
        'TASK_CATEGORY' => $category_id,
        'TASK_TYPE'     => $task_type,
    )
);
