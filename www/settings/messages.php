<?php
use Monolog\Logger;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Realtime\Message\RerouteOverride;
use Schoolbox\Realtime\Message\RerouteOverrideMapper;
use Schoolbox\Resource\CategoryMapper;
use Schoolbox\User;

/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

/** @var SitePage $this */

/* DATA RETRIEVAL AND PARSING */

/** @var User $user */
$user = $_SESSION['user'];

// as much as we'd like get this as cached in session, we can't include subjects here
$accesses   = UserAccessMapper::getInstance()->findAllForUser($user, false);

/** @var \Schoolbox\Collection|\Schoolbox\Resource\Category[] $cats */
$cats       = CategoryMapper::getInstance()
                            ->findByColumn('id', $accesses
                            ->getColumnValues('serviceIdentifier'))
                            ->sortBy('name', 'asc');

$catIds     = $cats->getColumnValues('id');
$service    = UserAccess::SERVICE_HOMEPAGE;
$search     = array(
    'msg_route_key'  => "service.$service.([0-9]+)",
    'outgoing_level' => 0
);

$muted = array(
    'unfollow' => $user->getId(),
    'disabled' => 0
);

$routeKeyRegex = "/service\\.$service\\.([0-9]+)/";
foreach ($muted as $k => $id) {
    $muted[$k] = RerouteOverrideMapper::getInstance()
        ->getMatchingRoutes("service.$service", 0, $id)
        ->map(
            function($override) use ($routeKeyRegex) {
                /** @var RerouteOverride $override */
                preg_match($routeKeyRegex, $override->getRouteKey(), $matches);
                return is_array($matches) ? $matches[1] : null;
            },
            false
        )->getAll();
}


$stmt = Database::getInstance()->prepare("
	SELECT  ns.slug, ns.label, src.key, src.description, perm.min_level, perm.max_level,
			perm.default_level, reroute.outgoing_level
	FROM msg_source src
	LEFT JOIN groups g ON g.`group_type` = src.`role_type`
	LEFT JOIN msg_route_perm perm ON src.id = perm.msg_source_id AND g.`id` = perm.`role_id`
	LEFT JOIN msg_namespace ns ON ns.id = src.namespace
	LEFT JOIN msg_reroute reroute ON src.id = reroute.msg_source_id AND reroute.user_id = :user_id
	WHERE perm.role_id = :role_id
	ORDER BY min_level < max_level DESC, `key` ASC
");


$stmt->execute(array(
	'user_id' => $user->getId(),
	'role_id' => $user->getRole()->getId()
));

$data = array();

while($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
	$data[$r['slug']][$r['key']] = $r;
}

$methods = array(
	0               => 'off',
	Logger::INFO    => 'digest',
	Logger::NOTICE  => 'instant',
	Logger::WARNING => 'email',
);

/* DISPLAY CONTENT */

$this->setTitle('Message Settings');
$this->setFile(TEMPLATE_content, 'settings/messages.html');

$this->setBlock(TEMPLATE_content, 'group', 'group_block');
$this->setBlock('group', 'follow', 'follow_block');

$this->setBlock(TEMPLATE_content, 'digestible', 'digestible_block');
$this->setBlock(TEMPLATE_content, 'digestible_col', 'digestible_col_block');
$this->setBlock(TEMPLATE_content, 'namespace', 'namespace_block');
$this->setBlock('namespace', 'route', 'route_block');
$this->setBlock('route', 'box', 'box_block');


// following
foreach ($cats as $cat) {

    $forced = false;

    // forced hydration
    $cat = CategoryMapper::getInstance()->findById($cat->getId());

    if ($grp = $cat->getGroup()) {
        $forced = $grp->getForceFollow() || in_array($cat->getId(), $muted['disabled']);
    }

    $follow = !(in_array($cat->getId(), $muted['unfollow']) || in_array($cat->getId(), $muted['disabled']));

    $this->setVar(array(
        'CATEGORY_NAME'               => $cat->getName(),
        'CATEGORY_ID'                 => $cat->getId(),
        'CATEGORY_FOLLOWING_FORCE'    => $forced ? '1' : '0',
        'CATEGORY_FOLLOWING_CHECKED'  => $follow ? 'checked="checked"'   : '' ,
        'CATEGORY_FOLLOWING_DISABLED' => $forced ? 'disabled="disabled"' : ''
    ));
    $this->parse('follow_block', 'follow', true);
}

if (count($cats)) {
    $this->parse('group_block', 'group');
} else {
    $this->setVar('group_block', '');
}


// digest
$digestFreq = $user->getRole()->getDigestSchedule();

try {
	$cron = \Cron\CronExpression::factory($digestFreq);
	$digest = true;

	// parse out a human-readable form of the cron
	$test = array(
		0 => 'Sundays',
		1 => 'Mondays',
		2 => 'Tuesdays',
		3 => 'Wednesdays',
		4 => 'Thursdays',
		5 => 'Fridays',
		6 => 'Saturdays'
	);
	$days = explode(',', $cron->getExpression(\Cron\CronExpression::WEEKDAY));

	// no diff: every day
	if(!array_diff(array_keys($test), $days)) {
		$fq = "every day";
	}
	// matches middle 5: weekdays
	elseif(!array_diff(array_slice(array_keys($test), 1, 5), $days)) {
		$fq = "on weekdays";
	}
	else {
		$fq = 'on ' . implode(', ', array_intersect_key($test, $days));
        if (($pos = strrpos($fq, ',')) !== false) {
            $fq = substr_replace($fq, ' &amp;', $pos, 1);
        }
	}

	$fq .= sprintf(' at %s', date('g:ia', strtotime(
			$cron->getExpression(\Cron\CronExpression::HOUR) . ":" .
			$cron->getExpression(\Cron\CronExpression::MINUTE)
		))
	);

	$this->setVar('DIGEST_FREQUENCY', $fq);
	$this->parse('digestible_block','digestible');
	$this->parse('digestible_col_block','digestible_col');
}
catch(InvalidArgumentException $e) {
	$digest = false;
	$this->setVar(array(
		'digestible_block' => '',
		'digestible_col_block' => '',
	));
	unset($methods[Logger::INFO]);
}

$this->setVar('namespace_block', '');

foreach($data as $namespace => $routes) {

	$this->setVar('NAMESPACE_SLUG', $namespace);

	$this->setVar('route_block', '');
	foreach($routes as $key => $r) {

		$this->setVar(array(
			'NAMESPACE_LABEL' => $r['label'],
			'ROUTE_DESCR'     => $r['description'],
			'KEY'             => $key,
		));

		// locked
		if($r['min_level'] == $r['max_level']) {
			$myLevel = $r['min_level'];
			$onlyLevel = true;
		}
		else {
			$myLevel = is_null($r['outgoing_level']) ? max($r['default_level'], $r['min_level']) : $r['outgoing_level'];
			$onlyLevel = false;
		}

		// if digest specified, but digest is unavailable, escalate to instant
		if($myLevel == Logger::INFO && empty($methods[Logger::INFO])) {
			$myLevel = Logger::NOTICE;
		}

//		var_dump(array( $r['min_level'], $myLevel, $r['max_level'] ));

		$this->setVar('box_block', '');
		foreach($methods as $level => $method) {

			$checked = $level == $myLevel;
			$disabled = ($level < $r['min_level'] || $level > $r['max_level'] || $onlyLevel);

			$this->setVar(array(
				'METHOD'      => $level,
				'CHECKED'     => $checked ? 'checked="checked"' : '',
				'DISABLED'    => $disabled ? 'disabled="disabled"' : '',
				'ROUTE_CLASS' => $onlyLevel ? 'advanced' : '',

			));

			$this->parse('box_block', 'box', true);
		}
		$this->parse('route_block', 'route', true);
	}
	$this->parse('namespace_block', 'namespace', true);
}
