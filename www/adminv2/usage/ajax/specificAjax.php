<?php

namespace Schoolbox\Usage\Specific;

/** @var SitePage $this */
if (!$this->getDi()->get('session')->get('user')->isSuperuser()) {
    $this->error(\Schoolbox\Error\Mapper::ERROR_PERM_PAGE_VIEW, '', 403 );
}

$usage = new AjaxFlot($_GET['userId']);

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
	case 'files':
		echo $usage->findFileUsage($_GET['orderBy'], $_GET['order'], $_GET['index'], $_GET['numPerPage']);
		break;
	default: break;
}

exit();
