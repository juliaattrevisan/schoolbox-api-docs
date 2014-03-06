<?php
use Monolog\Logger;
use Schoolbox\Role;
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

$stmt = Database::getInstance()->prepare("
	SELECT  m_ns.slug, m_ns.label, m_src.key, m_src.description, m_perm.min_level, m_perm.max_level,
			m_perm.default_level, m_rr.incoming_level, m_rr.outgoing_level
	FROM msg_source m_src
	LEFT JOIN msg_route_perm m_perm ON m_src.id = m_perm.msg_source_id
	LEFT JOIN msg_namespace m_ns ON m_ns.id = m_src.namespace
	LEFT JOIN msg_reroute m_rr ON m_src.routing_key = m_rr.routing_key AND m_rr.relation_type = 'user' AND m_rr.relation_id = :user_id
	WHERE m_perm.role_id = :role_id
	ORDER BY min_level < max_level DESC, `key` ASC
");

/** @var User $user */
$user = $_SESSION['user'];

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

$this->setFile(TEMPLATE_content, 'settings/messages.html');

$this->setBlock(TEMPLATE_content, 'digestible', 'digestible_block');
$this->setBlock(TEMPLATE_content, 'digestible_col', 'digestible_col_block');
$this->setBlock(TEMPLATE_content, 'namespace', 'namespace_block');
$this->setBlock('namespace', 'route', 'route_block');
$this->setBlock('route', 'box', 'box_block');

// digest
if($digestFreq = $user->getRole()->getDigestSchedule()) {
	$digest = true;
	$fq = $digestFreq == Role::DIGEST_DAILY ? 'daily' : 'weekly';

	$this->setVar('DIGEST_FREQUENCY', $fq);
	$this->parse('digestible_block','digestible');
	$this->parse('digestible_col_block','digestible_col');
}
// no digest
else {
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
			$myLevel = $r['outgoing_level'] ?: max($r['default_level'], $r['min_level']);
			$onlyLevel = false;
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
