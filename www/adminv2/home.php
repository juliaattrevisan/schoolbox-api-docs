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

/* Disk space */
$total = disk_total_space(Cfg::get('file_dir'));
$free = disk_free_space(Cfg::get('file_dir'));
$used = $total - $free;
$procPerc = round(($used / $total) * 100);
$procGradient = 10 - floor($procPerc / 10);

$disk = array(
    'DISK_USED' => Alaress_Byte_Format::bytesToString($used),
    'DISK_TOTAL' => Alaress_Byte_Format::bytesToString($total),
    'DISK_PERC' => $procPerc,
    'DISK_GRADIENT' => $procGradient
);
$this->setVar($disk);

/* Memory Usage */
if(is_readable('/proc/meminfo')) {
    foreach(file('/proc/meminfo') as $ri) {
        $m[strtok($ri, ':')] = str_replace(' kB','',strtok(''))*1024;
    }
    $usedMem = $m['MemTotal'] - $m['MemFree'] - $m['Cached'];
    $memPerc = round($usedMem / $m['MemTotal'] * 100);
    $memGradient = 10 - floor($memPerc / 10);

    $memory = array(
        'MEMORY_USED' => Alaress_Byte_Format::bytesToString($usedMem),
        'MEMORY_TOTAL' => Alaress_Byte_Format::bytesToString($m['MemTotal']),
        'MEMORY_PERC' => $memPerc,
        'MEMORY_GRADIENT' => $memGradient
    );
    $this->setVar($memory);
//    $this->parse('memory_block', 'memory');
} else {
//    $this->setVar('memory_block', '');
}

/* External system checks */
if(Cfg::get('external_enable')) {
    $external_ping = new External_Ping();
    if ($external_ping->isResponsive()) {
        $db = array('EXTERNAL_DB'   => ucfirst(Cfg::get('external_type')),
                    'EXTERNAL_DB_LIGHT' => 'success',
                    'EXTERNAL_DB_STATUS' => 'Online',);
    } else {
        $db = array('EXTERNAL_DB'   => ucfirst(Cfg::get('external_type')),
                    'EXTERNAL_DB_LIGHT' => 'alert',
                    'EXTERNAL_DB_STATUS' => 'Offline',);
    }
} else {
    $db = array('EXTERNAL_DB'   => ucfirst(Cfg::get('external_type')),
            'EXTERNAL_DB_LIGHT' => 'alert',
            'EXTERNAL_DB_STATUS' => 'Disabled',);
}
$this->setVar($db);

/* Email server check */
if(Cfg::get('mail_enable') && Cfg::get('mail_smtp_host')) {
    $socket=socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => 1, 'usec' => 0));
    if(@socket_connect($socket, Cfg::get('mail_smtp_host'), Cfg::get('mail_smtp_port'))) {
       $email = array('EMAIL_LIGHT'    => 'success',
                       'EMAIL_STATUS'    => 'Online');
    } else {
       $email = array('EMAIL_LIGHT'    => 'alert',
                      'EMAIL_STATUS'    => 'Offline');
    }
    socket_close($socket);
} else {
       $email = array('EMAIL_LIGHT'    => 'alert',
                'EMAIL_STATUS'    => 'Disabled');
}
$this->setVar($email);

/* LDAP server check */
$ldap1 = array('LDAP_LIGHT'     => 'alert',
                'LDAP_STATUS'    => 'Disabled');
$ldap2 = array('LDAP_ALT_LIGHT' => 'alert',
                'LDAP_ALT_STATUS'=> 'Disabled');
if(Cfg::get('ldap_enable')) {
    $tempsession = new Session;

    // Check primary LDAP server bind
    if (trim(Cfg::get('ldap_host')) != "") {
        $ds=ldap_connect(Cfg::get('ldap_host'));
        ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ds, LDAP_OPT_REFERRALS, 0);
        $ldapbind = @ldap_bind($ds, $tempsession->ldap_escape(Cfg::get('ldap_user')), utf8_encode(Cfg::get('ldap_pass')));
        if($ldapbind) {
            $ldap1 = array('LDAP_LIGHT'    => 'success',
                        'LDAP_STATUS'    => 'Online');
        } else {
            $ldap1 = array('LDAP_LIGHT'    => 'alert',
                        'LDAP_STATUS'    => 'Offline');
        }
        unset($ds,$ldapbind);
    }

    // Check secondary LDAP server bind
    if (trim(Cfg::get('ldap_host_alt')) != "") {
        $ds=ldap_connect(Cfg::get('ldap_host_alt'));
        ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ds, LDAP_OPT_REFERRALS, 0);
        $ldapbind = @ldap_bind($ds, $tempsession->ldap_escape(Cfg::get('ldap_user_alt')), utf8_encode(Cfg::get('ldap_pass')));
        if($ldapbind) {
            $ldap2 = array('LDAP_ALT_LIGHT'    => 'success',
                        'LDAP_ALT_STATUS'    => 'Online');
        } else {
            $ldap2 = array('LDAP_ALT_LIGHT'    => 'alert',
                        'LDAP_ALT_STATUS'    => 'Offline');
        }
        unset($ds,$ldapbind);
    }
}
$this->setVar($ldap1);
$this->setVar($ldap2);
unset($tempsession);

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
