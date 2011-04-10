<?php


require_once SITE_root . '/library/user.php';
require_once SITE_root . '/library/group.php';
$userHandler = new User;
$groupHandler = new Group;

/* Superusers only */
if(!$groupHandler->isSuperuser())
    error(19);

if(is_numeric($this->arg[0])) {

    $this->setFile(TEMPLATE_content, 'adminv2/modify-user.html');

    /* Modify existing user */
    $user = $userHandler->getByID($this->arg[0]);

    if(is_array($user)) {
        $output = array('USERID'            => $user['uid'],
                        'FIRSTNAME'         => $user['firstname'],
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


        /* Output title list */
        $this->dropDown('title', $userHandler->getTitleList(), $user['title'], TEMPLATE_content, array('titleName'=>'NAME_TITLE'));
        
        /* Output group list */
         $this->dropDown('system_group', $userHandler->getGroupList(), $user['group_id'], TEMPLATE_content, array('id'=>'GROUP_ID','name'=>'SYSTEM_GROUP_NAME'));
        
        /* Output campus list */
         $this->setBlock(TEMPLATE_content, 'campus','campus_block');
         $campusList = $userHandler->getCampusList();
         $userCampus = $userHandler->getUserCampus($user['uid']);
         foreach($campusList as $campus ) {

             $outcampus = array('CAMPUS_ID'         => $campus['id'],
                            'CAMPUS_NAME'           => $campus['name'],
                            'CAMPUS_ID_CHECKED'     => '',
                            'CAMPUS_DEFAULT_CHECKED'    => '',);

             if(($key = array_search($campus['id'],$userCampus)) !== FALSE) {
                $outcampus['CAMPUS_ID_CHECKED'] = 'checked="checked"';
                if($key==0)
                    $outcampus['CAMPUS_DEFAULT_CHECKED'] = 'checked="checked"';

             }


             $this->setVar($outcampus);
             $this->parse('campus_block','campus',true);
         }


         /* Output group list */
         $this->setBlock(TEMPLATE_content, 'user_group', 'user_group_block');
         $groupList = $groupHandler->involvedCategoryList($user['uid']);
         if(is_array($groupList)) {
             foreach($groupList as $group) {
                 $outputGroup = array('USER_GROUP_NAME'  => $group['name'],
                                      );

                 $this->setVar($outputGroup);
                 $this->parse('user_group_block','user_group',true);
             }
         }
         else {
             $this->setVar('user_group_block','');
         }

         /* Output permissions */
        $this->setBlock(TEMPLATE_content, 'login_log', 'login_log_block');
        $loginLog = $userHandler->getUserLogins($user['username']);
        if(is_array($loginLog)) {
            foreach($loginLog as $result) {
                    $output = array (   'LOGIN_DATE' 	=> date('d/m/Y G:i',strtotime( $result['date'] )),
                                        'LOGIN_IP' => $result['ip'],
                                        'LOGIN_RESULT' => $result['login']?'Success':'Failed',
                                                    );
                    $this->setVar($output);
                    $this->parse('login_log_block', 'login_log', true);
            }
        }
        else
            $this->setVar('login_log_block', '');

         /* Output login log */


         /* Output activity log */
         $this->setBlock(TEMPLATE_content, 'action_log', 'action_log_block');
         $activityLog = $userHandler->getUserActivity($user['uid'],10);
         $lookupAction = array('create'=>'Created',
                                'modify'=>'Modified',
                                'delete'=>'Deleted');

        if(is_array($activityLog)) {
            foreach($activityLog as $result) {
                    $output = array (	'ACTION_DATE' 	=> date('d/m/Y G:i',strtotime( $result['time'] )),
                                            'ACTION_ACTION' => $lookupAction[$result['action']].' '.$result['object'].' '.$result[$result['object']],
                                                    );
                    $this->setVar($output);
                    $this->parse('action_log_block', 'action_log', true);
            }
        }
        else
                $this->setVar('action_log_block', '');

    }
}
else {
    $this->setFile(TEMPLATE_content, 'adminv2/new-user.html');

    $this->setVar('DATABASE_TYPE', ucfirst(Cfg::get('external_type')));

    /* Output title list */
    $this->dropDown('title', $userHandler->getTitleList(),'', TEMPLATE_content, array('titleName'=>'NAME_TITLE'));

    /* Output group list */
     $this->dropDown('system_group', $userHandler->getGroupList(), '', TEMPLATE_content, array('id'=>'GROUP_ID','name'=>'SYSTEM_GROUP_NAME'));

    /* Output campus list */
     $this->setBlock(TEMPLATE_content, 'campus','campus_block');
     $campusList = $userHandler->getCampusList();
     foreach($campusList as $campus ) {

         $outcampus = array('CAMPUS_ID'         => $campus['id'],
                        'CAMPUS_NAME'           => $campus['name'],
                        'CAMPUS_ID_CHECKED'     => '',
                        'CAMPUS_DEFAULT_CHECKED'    => '',);

         $this->setVar($outcampus);
         $this->parse('campus_block','campus',true);
     }
}
?>
