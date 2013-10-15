<?php

namespace Schoolbox\Usage;

$usage = new OverallStorage\AjaxFlot();

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
		echo $usage->findUserUsage('bytes', 'DESC', 0, 8);
		break;
	default: break;
}

exit();
