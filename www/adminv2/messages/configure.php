<?php
use Monolog\Logger;
use Schoolbox\Role;

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

if(!isset($this)) {
    require_once '../../../include/Bootstrap.php';
    if(!isset($_POST)) {
        SitePage::localRedir('/adminv2/messages/configure');
    }

    // you gotst permishunz?
    session_start();

    if(!$_SESSION['user']->isSuperuser()) {
        error(19);
    }


    header('Content-Type: application/json');
    // do ajax

    $data = array($_POST);

    try {

        foreach($_POST['data'] as $namespace => $keys) {
            foreach($keys as $key => $roleIds) {
                foreach($roleIds as $roleId => $state) {

                    $enabled = !!$state['enabled'];
                    $levels  = isset($state['level']) ? $state['level'] : array();

                    $data[] = array($namespace, $key, $roleId, $enabled, $levels);

                    if($enabled) {

                        $query = "
							INSERT INTO msg_route_perm (
							    msg_source_id, role_id, min_level, max_level, default_level
							) VALUES (
							    (
							        SELECT DISTINCT src.id
							        FROM msg_source src
							        LEFT JOIN msg_namespace ns ON src.namespace = ns.id
							        LEFT JOIN groups g ON src.role_type = g.group_type
							        WHERE src.key = :key
							        AND ns.slug = :slug
							        AND g.id = :role_id
							    ),
							    :role_id, :min, :max, :default
							)
							ON DUPLICATE KEY UPDATE
							    min_level = :min,
							    max_level = :max,
							    default_level = :default;
						";


                        // insert / update so it will show up for users of that group
                        $stmt = Database::getInstance()->prepare($query);
                        $stmt->execute(array(
                            'min'     => $levels['min'],
                            'max'     => $levels['max'],
                            'default' => $levels['default'],
                            'role_id' => $roleId,
                            'key'     => $key,
                            'slug'    => $namespace
                        ));

                    } else {
                        // delete
                        $query = "
							DELETE FROM msg_route_perm
							WHERE msg_source_id = (
								SELECT src.id
								FROM msg_source src
								LEFT JOIN msg_namespace ns ON src.namespace = ns.id
								LEFT JOIN groups g on src.role_type = g.group_type
								WHERE src.key = :key
								AND ns.slug = :slug
								AND g.id = :role_id
							)
							AND role_id = :role_id
						";

                        $stmt = Database::getInstance()->prepare($query);
                        $stmt->execute(array(
                            'role_id' => $roleId,
                            'key'     => $key,
                            'slug'    => $namespace
                        ));
                    }
                }
            }
        }

    } catch(Exception $e) {
        header(':', true, 500);
        $data[] = $e->getTrace();
    }

    echo json_encode($data);

    exit;
}


if(!$_SESSION['user']->isSuperuser()) {
    error(19);
}

/** @var SitePage $this */

$this->setFile(TEMPLATE_content, '/adminv2/messages/configure.html');
$nav = new AdminV2_Nav();
$nav->parse($this, 'messages');
$this->setTitle('Admin - Messages');

$db = Database::getInstance();

// if arg[0] is set and is a valid roletype, put $filter, otherwise null
$roletypes = array('parent','student','staff');
$filter = isset($this->arg[0]) && in_array($this->arg[0], $roletypes) ? $this->arg[0] : null;

if($filter) {
    $stmt = $db->prepare("
		SELECT *
		FROM msg_namespace ns
		LEFT JOIN msg_source src ON ns.id = src.namespace
		LEFT JOIN groups g ON src.role_type = g.group_type
		LEFT JOIN msg_route_perm perm ON src.id = perm.msg_source_id AND g.id = perm.role_id
		WHERE g.group_type = ?
		ORDER BY slug, src.key, g.id
	");
    $stmt->execute(array($filter));
} else {
    $stmt = $db->prepare("
		SELECT *
		FROM msg_namespace ns
		LEFT JOIN msg_source src ON ns.id = src.namespace
		LEFT JOIN groups g ON src.role_type = g.group_type
		LEFT JOIN msg_route_perm perm ON src.id = perm.msg_source_id AND g.id = perm.role_id
		ORDER BY slug, src.key, g.id
	");
    $stmt->execute();

    // override blank to the string 'all' for display
    $filter = 'all';
}

$data = array();

// build yo data structure
while($r = $stmt->fetch(PDO::FETCH_NUM)) {
    $r = slice($r);

    // identifiers
    $slug = $r['ns']['slug'];
    $source = $r['source']['key'] . sha1($r['source']['description']);
    $role = $r['role']['id'];


    //// NAMESPACE ////

//	if(is_null($slug)) {
//		continue;
//	}
    if(!isset($data['namespace'][$slug])) {
        $data['namespace'][$slug] = $r['ns'];
        $data['namespace'][$slug]['source'] = array();
    }
    $dns = &$data['namespace'][$slug];


    //// SOURCE ////

//	if(is_null($source)) {
//		continue;
//	}
    if(!isset($dns['source'][$source])) {
        $dns['source'][$source] = $r['source'];
        $dns['source'][$source]['roles'] = array();
    }
    $dsource = &$dns['source'][$source];


    //// ROLE ////

    if(!isset($dsource['roles'][$role])) {
        $dsource['roles'][$role] = $r['role'];
        $dsource['roles'][$role]['perms'] = array();
    }
    $drole = &$dsource['roles'][$role];


    //// PERM ////

//	if(is_null($r['perm']['min_level'])) {
//		continue;
//	}
    $drole['perms'][] = $r['perm'];

}

$methods = array(
    0               => 'Off',
    Logger::INFO    => 'Digest',
    Logger::NOTICE  => 'Instant',
    Logger::WARNING => '+ Email',
);


$this->setBlock(TEMPLATE_content, 'filter', 'filter_block');
$this->setBlock(TEMPLATE_content, 'namespace', 'namespace_block');
$this->setBlock('namespace', 'source', 'source_block');
$this->setBlock('source', 'existing_source', 'existing_source_block');
$this->setBlock('source', 'source_editable', 'source_editable_block');
$this->setBlock('source', 'source_readonly', 'source_readonly_block');
$this->setBlock('existing_source', 'role', 'role_block');
$this->setBlock('role', 'perm', 'perm_block');
$this->setBlock('role', 'perm_enabled', 'perm_enabled_block');
$this->setBlock('role', 'perm_option', 'perm_option_block');


foreach(array_merge(array('all'), $roletypes) as $type) {
    $output = array(
        'FILTER_NAME' => ucfirst($type),
        'FILTER_SLUG' => $type == 'all' ? '' : $type,
    );

    // if this is the currently selected filter
    if($filter == $type) {
        $output['FILTER_CLASS'] = 'current';
    } else {
        $output['FILTER_CLASS'] = '';
    }

    $this->setVar($output);
    $this->parse('filter_block', 'filter', true);
}

foreach($data['namespace'] as $namespace) {
    $this->setVar(array(
        'NAMESPACE_ID'    => $namespace['id'],
        'NAMESPACE_SLUG'  => $namespace['slug'],
        'NAMESPACE_LABEL' => $namespace['label'],

        'source_block'    => ''
    ));

    foreach($namespace['source'] as $source) {
        $this->setVar(array(
            'SOURCE_KEY' => $source['key'],
            'SOURCE_DESCRIPTION' => $source['description'],
            'SOURCE_ROUTING_KEY' => $source['routing_key'],

            'role_block' => ''
        ));

        if($source['key']) {
            foreach($source['roles'] as $role) {
                $this->setVar(array(
                    'ROLE_ID'   => $role['id'],
                    'ROLE_NAME' => Scrub::text($role['name']),

                    'perm_block' => '',
                    'perm_option_block' => '',
                    'perm_enabled_block' => ''
                ));

                foreach($role['perms'] as $perm) {

                    foreach(array('Off', 'On') as $value => $label) {
                        $this->setVar(array(
                            'PERM_TOGGLE_VALUE' => $value,
                            'PERM_TOGGLE_LABEL' => $label,
                            'PERM_TOGGLE_SELECTED' => is_null($perm['default']) != $value ? 'checked="checked"' : ''
                        ));
                        $this->parse('perm_enabled_block', 'perm_enabled', true);
                    }

                    foreach($methods as $level => $label) {
//						$disabled = ($level == Logger::INFO) && ($role['digest_schedule'] == Role::DIGEST_NEVER) && false;

                        $this->setVar(array(
                            'PERM_OPTION_LABEL'    => $label,
//							'PERM_OPTION_SELECTED' => $level == $levelValue ? 'checked="checked"' : '',
//							'PERM_OPTION_DISABLED' => $disabled ? 'disabled="disabled"' : '',
                        ));
                        $this->parse('perm_option_block', 'perm_option', true);
                    }

                    foreach($perm as $levelLabel => $levelValue) {
                        $this->setVar(array(
                            'PERM_LABEL'        => $levelLabel,
                            'PERM_OPTION_VALUE' => $levelValue,
                        ));
                        $this->parse('perm_block', 'perm', true);
                    }


                }
                $this->parse('role_block', 'role',  true);
            }
            $this->parse('existing_source_block', 'existing_source');
        } else {
            $this->setVar('existing_source_block','');
        }
        $this->parse('source_readonly_block','source_readonly');
        $this->setVar('source_editable_block');
        if($source['key']) {
            $this->parse('source_block', 'source', true);
        } else {
            $this->setVar('source_block', '<p>No events.</p>');
        }
    }
    $this->parse('namespace_block', 'namespace', true);
}




function slice($flat)
{
    $map = array(
        'ns'      => array('id','slug','label'),
        'source'  => array('id','','key','role_type','description'),
        'role'    => array('id','name','','','digest_schedule'),
        'perm'    => array('','','min_level','max_level','default_level')
    );

    $offset = 0;

    $sliced = array();

    foreach($map as $label => $fields) {
        $sliced[$label] = array_combine($fields, array_slice($flat, $offset, count($fields)));
        $offset += count($fields);
        unset($sliced[$label]['']);
    }

    $sliced['perm'] = array(
        'max'     => $sliced['perm']['max_level'],
        'default' => $sliced['perm']['default_level'],
        'min'     => $sliced['perm']['min_level']
    );

    return $sliced;
}
