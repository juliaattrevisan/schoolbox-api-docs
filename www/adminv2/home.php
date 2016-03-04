<?php

$groupHandler = new Group();
$userHandler = new User();

if (!$groupHandler->isSuperuser()) {
    error(19);
}

$this->setFile(TEMPLATE_content, 'adminv2/home.html');
$this->setBlock(TEMPLATE_content, 'group_summary', 'group_summary_block');
$this->setBlock(TEMPLATE_content, 'recent_log', 'recent_log_block');
//$this->setBlock(TEMPLATE_content, 'memory', 'memory_block');
$this->setBlock(TEMPLATE_content, 'no_js_warning', 'no_js_warning_block');

// include the admin navigation
$nav = new AdminV2_Nav();
$nav->parse($this, 'home');

$this->setTitle('Admin - Home');



/* LDAP server check */

/* WWW access check */
if(in_array('nojscript', $this->arg)) {

    // Believe it or not, this is actually really slow!

    $context = \Proxy\Server::getInstance()->getContext();
    if(@file_get_contents('http://schoolbox.com.au/schoolboxinstaller/status.html', null, $context)) {
        $www = array('WWW_LIGHT'  => 'success',
                    'WWW_STATUS' => 'Available'
        );
    } else {
        $www = array('WWW_LIGHT'  => 'alert',
                    'WWW_STATUS' => 'Inaccessible');
    }

} else {
    $www = array('WWW_LIGHT'  => 'warning',
                 'WWW_STATUS' => 'Not Tested');
}
$this->setVar($www);

/* Group summary */
// only do this if js is disabled
// TODO: there has to be a better way to do this...
if(in_array('nojscript', $this->arg)) {

    $summary = $groupHandler->userSummary();
    $totalUser = 0;
    $totalLogin = 0;


    foreach($summary as $group) {
        $groupOut = array('GROUP_ID'        => $group['id'],
                          'GROUP_NAME'      => Scrub::text($group['name']),
                          'GROUP_USER'      => $group['total'],
                          'GROUP_LOGGED_IN' => $group['logged_in']);
        $totalUser += $group['total'];
        $totalLogin += $group['logged_in'];

        $this->setVar($groupOut);

        $this->parse('group_summary_block','group_summary',true);
    }

    $this->setVar( array(  'GROUP_ID'=>'',
        'GROUP_NAME'=>'Total',
        'GROUP_USER'=>$totalUser,
        'GROUP_LOGGED_IN'=>$totalLogin,));
    $this->setVar('no_js_warning_block', '');
    $this->parse('group_summary_block','group_summary',true);


} else {
    $this->setVar($groupOut);
    $this->setVar('group_summary_block', '');
    $this->parse('no_js_warning_block', 'no_js_warning');
}

/* Recent activity */
$query = "(SELECT `date` occurred, login, ip, title, firstname, lastname FROM login_log LEFT JOIN `user` ON `user`.`username`=login_log.username ORDER BY DATE DESC LIMIT 0,40)
UNION
(SELECT `time` occurred, `action`, object, title, firstname, lastname FROM cmsLog JOIN `user` ON `user`.id=`user` ORDER BY `time` DESC LIMIT 0,40)
ORDER BY occurred DESC LIMIT 0,30";
$recentLog = $this->db->getAll($query);

if(is_array($recentLog) ) {
    foreach($recentLog as $log) {

        $action = '';

        switch($log['login']) {
            case '1' :
                $action = 'Logged in';
            break;
            case '0' :
                $action = 'Failed login';
            break;
            case 'modify':
                $action = 'Modified '.$log['ip'];
            break;
            case 'access':
                $action = 'Accessed '.$log['ip'];
            break;
            case 'create' :
             $action = 'Added '.$log['ip'];
            break;
            case 'delete' :
             $action = 'Deleted '.$log['ip'];
            break;

            default:  //If unknown, skip output
                continue;
        }

        if($action != '') {

            $date = new Alaress_Date_Format($log['occurred'], Alaress_Date_Format::DATABASE_FORMAT);

            $output = array('LOG_DATE'      => $date->getShort(),
                            'LOG_ACTION'    => $action,
                            'LOG_NAME'      => $userHandler->formatName($log));
            $this->setVar($output);
            $this->parse('recent_log_block','recent_log',true);
        }
    }
}
