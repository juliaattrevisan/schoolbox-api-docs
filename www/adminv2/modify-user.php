<?php
$this->setFile(TEMPLATE_content, 'adminv2/modify-user.html');

require_once SITE_root . '/library/user.php';
$userHandler = new User;

if(is_numeric($this->arg[0])) {
    $user = $userHandler->getByID($this->arg[0]);

    if(is_array($user)) {
        $output = array('FIRSTNAME'         => $user['firstname'],
                        'USERNAME'          => $user['username'],
                        'FULLNAME'          => formatName($user),
                        'DATABASE_ID'       => $user['synergy_id'],
                        'SUPERUSER_CHECKED' => $user['su']==1?'checked="checked"':'',
                        'LASTNAME'          => $user['lastname'],
                        'EPORTFOLIO_CHECKED'=> $user['eportfolio']==1?'checked="checked"':'',
                        'YEAR'              => $user['year'],
                        'DATABASE_TYPE'     => ucfirst(Cfg::get('external_type')),
            );
        $this->setVar($output);



   }
}
else {
    error(2);
}
?>
