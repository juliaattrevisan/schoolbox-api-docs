<?php

namespace Schoolbox\Usage\All;

/** @var SitePage $this */
if (!$this->getDi()->get('session')->get('user')->isSuperuser()) {
    $this->error(\Schoolbox\Error\Mapper::ERROR_PERM_PAGE_VIEW, null, 403 );
}

$usage = new AjaxFlot();

switch($_GET['type']) {
	case 'total':
		echo $usage->findTotalUsage();
		break;
	case 'group':
		echo $usage->findGroupUsage();
		break;
	case 'type':
		echo $usage->findTypeUsage();
		break;
	case 'user':
		echo $usage->findUserUsage($_GET['orderBy'], $_GET['order'], $_GET['index'], $_GET['numPerPage']);
		break;
	default: break;
}

exit();
