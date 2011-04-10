<?php
require_once SITE_root . '/library/group.php';

$this->setFile(TEMPLATE_content, 'adminv2/home.html');

$this->setBlock(TEMPLATE_content, 'group_summary', 'group_summary_block');
$this->setBlock(TEMPLATE_content, 'recent_log', 'recent_log_block');

$groupHandler = new Group;

if(!$groupHandler->isSuperuser())
    error(19);

/* Disk space */
$total = disk_total_space(Cfg::get('file_dir'));
$free = disk_free_space(Cfg::get('file_dir'));
$used = $total - $free;
$perc = round(($used/$total)*100);

$disk = array('DISK_USED'   => bytesToString($used),
                'DISK_TOTAL' => bytesToString($total),
                'DISK_PERC' => $perc);
$this->setVar($disk);

/* Memory Usage */
if(is_readable('/proc/meminfo')) {
    foreach(file('/proc/meminfo') as $ri)
        $m[strtok($ri, ':')] = str_replace(' kB','',strtok(''))*1024;

    $usedMem = $m['MemTotal'] - $m['MemFree'] - $m['Cached'];
    $memory = array('MEMORY_USED'   => bytesToString($usedMem),
                    'MEMORY_TOTAL'  => bytesToString($m['MemTotal']),
                    'MEMORY_PERC'   => round($usedMem / $m['MemTotal'] * 100));
    $this->setVar($memory);
}

/* External system checks */
if(Cfg::get('external_enable')) {
    $dbf = @mssql_connect(Cfg::get('external_db_host'), Cfg::get('external_db_user'),  Cfg::get('external_db_passwd'));
    if($dbf) {
        $db = array('EXTERNAL_DB'   => ucfirst(Cfg::get('external_type')),
                    'EXTERNAL_DB_LIGHT' => 'on',
                    'EXTERNAL_DB_STATUS' => 'Online',);
    }
    else {
        $db = array('EXTERNAL_DB'   => ucfirst(Cfg::get('external_type')),
                    'EXTERNAL_DB_LIGHT' => '',
                    'EXTERNAL_DB_STATUS' => 'Offline',);
    }
}
else {
    $db = array('EXTERNAL_DB'   => ucfirst(Cfg::get('external_type')),
            'EXTERNAL_DB_LIGHT' => '',
            'EXTERNAL_DB_STATUS' => 'Disabled',);
}
$this->setVar($db);

/* Email server check */
if(Cfg::get('mail_enable')) {
    $socket=socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if(socket_connect($socket, Cfg::get('mail_smtp_host'), Cfg::get('mail_smtp_port'))) {
       $email = array('EMAIL_LIGHT'    => 'on',
                       'EMAIL_STATUS'    => 'Online');
    }
    else {
       $email = array('EMAIL_LIGHT'    => '',
                      'EMAIL_STATUS'    => 'Offline');   
    }
    socket_close($socket);
}
else {
       $email = array('EMAIL_LIGHT'    => '',
                'EMAIL_STATUS'    => 'Disabled');
}
$this->setVar($email);

/* Ldap server check */
if(Cfg::get('ldap_enable')) {
    $ds=ldap_connect(Cfg::get('ldap_host'));
    if($ds) {
        $ldap = array('LDAP_LIGHT'    => 'on',
                     'LDAP_STATUS'    => 'Online');
    }
    else {
        $ldap = array('LDAP_LIGHT'    => '',
                      'LDAP_STATUS'    => 'Offline');
    }
}
else {
   $ldap = array('LDAP_LIGHT'    => '',
                  'LDAP_STATUS'    => 'Disabled');
}
$this->setVar($ldap);

/* WWW access check */
if(file_get_contents('http://schoolbox.com.au/design/img/favicon.ico')) {
   $www = array('WWW_LIGHT'    => 'on',
                'WWW_STATUS'    => 'Available');
}
else {
   $www = array('WWW_LIGHT'    => '',
                'WWW_STATUS'    => 'Inaccessible');
}
$this->setVar($www);


/* Group summary */
$summary = $groupHandler->userSummary();

foreach($summary as $group) {
    $groupOut = array('GROUP_NAME' => $group['name'],
                    'GROUP_USER'    => $group['total'],
                    'GROUP_LOGGED_IN' => $group['logged_in']);
    $this->setVar($groupOut);

    $this->parse('group_summary_block','group_summary',true);
}

/* Recent activity */
$query = "(SELECT `date` occurred, login, ip, title, firstname, lastname  FROM login_log JOIN `user` ON `user`.`username`=login_log.username ORDER BY DATE DESC LIMIT 0,40)
UNION
(SELECT `time` occurred, `action`, object, title, firstname, lastname FROM cmsLog JOIN `user` ON `user`.id=`user` ORDER BY `time` DESC LIMIT 0,40)
ORDER BY occurred DESC LIMIT 0,20";
$recentLog = $this->db->getAll($query);
if(is_array($recentLog) ) {
    foreach($recentLog as $log) {
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
            case 'create' :
             $action = 'Added '.$log['ip'];
            break;
            case 'delete' :
             $action = 'Deleted '.$log['ip'];
            break;

        }

        $output = array('LOG_DATE'      => date(Cfg::get('date_short'), strtotime($log['occurred'])),
                        'LOG_ACTION'    => $action,
                        'LOG_NAME'      => formatName($log));
        $this->setVar($output);
        $this->parse('recent_log_block','recent_log',true);
    }
}




?>