<?php

$userHandler = new User();
$groupHandler = new Group();

/* Superusers only */
if(!$groupHandler->isSuperuser())
    error(19);

if(is_numeric($this->arg[0])) {
    $this->setTitle('Admin - Modify User');
    $this->setFile(TEMPLATE_content, 'adminv2/modify-user.html');

    /* Permissions clone
	 */
    $this->setBlock(TEMPLATE_content, 'campusPermsList','campusPermsList_block');
    $this->setBlock(TEMPLATE_content, 'residentialHousePermsList','residentialHousePermsList_block');

    /* Output permissions
	 */
    $this->setBlock(TEMPLATE_content, 'user_calendar', 'user_calendar_block');
    $this->setBlock(TEMPLATE_content, 'user_news', 'user_news_block');
    $this->setBlock(TEMPLATE_content, 'user_cms', 'user_cms_block');
    $this->setBlock(TEMPLATE_content, 'user_housemaster', 'user_housemaster_block');
    $this->setBlock(TEMPLATE_content, 'user_resource_booking', 'user_resource_booking_block');
    $this->setBlock(TEMPLATE_content, 'user_curriculum', 'user_curriculum_block');

    // include the admin navigation
    $nav = new AdminV2_Nav();
    $nav->parse($this, 'user');

    $this->setVar(array(
        'user_calendar_block' => '',
        'user_news_block' => '',
        'user_cms_block'=> '',
        'user_housemaster_block'=> '',
        'user_resource_booking_block'=> '',
        'user_curriculum_block'=> '',
    ));

    /* Modify existing user
	 */
    $user = $userHandler->getByID($this->arg[0]);

    // TODO: remove double handling while we convert
    $userObj = \Schoolbox\UserMapper::getInstance()->findById($this->arg[0]);

    if (is_array($user)) {

        /* Prepend a please select at the start of every campus list
		 */
        $campusList = $userHandler->getCampusList();

        /* User data
		 */
        $output = array('USERID'            => $user['uid'],
                        'FIRSTNAME'         => $user['firstname'],
                        'USERNAME'          => $user['username'],
                        'ESCAPE_FIRSTNAME'  => addslashes($user['firstname']),
                        'ESCAPE_LASTNAME'   => addslashes($user['lastname']),
                        'FULLNAME'          => $userHandler->formatName($user),
                        'ALT_EMAIL'         => $user['alt_email'],
                        'PASSWORD'          => 'donotchange',  // set to foreign string to avoid reveals and to indicate password has not been changed
                        'CLEAR_PASSWORD'     => $user['email_pass'],
                        'DATABASE_ID'       => $user['synergy_id'],
                        'SUPERUSER_CHECKED' => $user['su']==1?'checked="checked"':'',
                        'LASTNAME'          => $user['lastname'],
                        'EPORTFOLIO_CHECKED'=> $user['eportfolio_enabled']==1?'checked="checked"':'',
                        'LOCAL_EMAIL_CHECKED'=> $user['local_email']==1?'checked="checked"':'',
                        'WEBMAIL_CHECKED'   => $user['webmail']==1?'checked="checked"':'',
                        'MAILHANDLER_CHECKED'=> $user['mail_handler']==1?'checked="checked"':'',
                        'ACCOUNT_ENABLED_CHECKED'=> $user['enabled']==1?'checked="checked"':'',
                        'YEAR'              => $user['year'],
                        'DATABASE_TYPE'     => ucfirst(Cfg::get('external_type')),
                        'ASSUME_PROMPT'     => $user['enabled'] ? 'Login as user' : 'Account disabled',
                        'ASSUME_CLASS'      => $user['enabled'] ? 'submit' : 'disabled' // doesn't affect admin ability to assume user
            );
        $this->setVar($output);


        $this->setBlock(TEMPLATE_content, 'reveal_password','reveal_password_block');
        $this->setVar('reveal_password_block','');

        if(Cfg::get('reveal_password') && $user['email_pass']) {
            $this->parse('reveal_password_block','reveal_password');
        }

        /* Output title list */
        $this->dropDown('title', $userHandler->getTitleList(), $user['title'], TEMPLATE_content, array('titleName'=>'NAME_TITLE'));

        /* Output group list */
        $this->dropDown('system_group', $userHandler->getGroupList(), $user['group_id'], TEMPLATE_content, array('id'=>'GROUP_ID','name'=>'SYSTEM_GROUP_NAME'));

        /* Output house lists */
        /** @var HouseResidential[]|Schoolbox\Collection $residentialHouseList */
        $residentialHouseList = HouseResidentialMapper::getInstance()->findAll();
        $this->dropDown('house', HouseMapper::getInstance()->findAll(), $user['house'], TEMPLATE_content, array('id'=>'HOUSE_ID','house'=>'HOUSE_NAME'));
        $this->dropDown('residential_house', $residentialHouseList, $user['residential_house'], TEMPLATE_content, array('id' => 'RESIDENT_HOUSE_ID', 'house' => 'RESIDENT_HOUSE_NAME'));

        /* Output campus list */
        $this->setBlock(TEMPLATE_content, 'campus','campus_block');

        /* User campuses
		 */
        $userCampus = $userHandler->getUserCampus($user['uid']);

        foreach($userHandler->getCampusList() as $campus) {
            $outcampus = array(
                'CAMPUS_ID'         => $campus['id'],
                'CAMPUS_NAME'           => Scrub::text($campus['name']),
                'CAMPUS_ID_CHECKED'     => '',
                'CAMPUS_DEFAULT_CHECKED'    => '',
            );

            if (($key = array_search($campus['id'],$userCampus)) !== false) {
                $outcampus['CAMPUS_ID_CHECKED'] = 'checked="checked"';

                if ($key == 0) {
                    $outcampus['CAMPUS_DEFAULT_CHECKED'] = 'checked="checked"';
                }
            }

            $this->setVar($outcampus);
            $this->parse('campus_block','campus',true);
        }

         /* Output group list */
         $this->setBlock(TEMPLATE_content, 'user_group', 'user_group_block');
         $groupList = $groupHandler->involvedInCategoryList($user['uid'],false);

         if(count($groupList)) {
             foreach($groupList as $group) {
                 $outputGroup = array(
                     'USER_GROUP_ID'   => $group['id'],
                     'USER_GROUP_NAME' => $group['name'],
                     'READ_CHECKED'    => $group['type']==1?'checked="checked"':'',
                     'WRITE_CHECKED'   => $group['type']==2?'checked="checked"':'',
                     'ADMIN_CHECKED'   => $group['type']==3?'checked="checked"':'',
                     'READ_SELECTED'   => $group['type']==1?'selected':'',
                     'WRITE_SELECTED'  => $group['type']==2?'selected':'',
                     'ADMIN_SELECTED'  => $group['type']==3?'selected':'',
                 );

                 $this->setVar($outputGroup);
                 $this->parse('user_group_block','user_group',true);
             }
         } else {
             $this->setVar('user_group_block','');
         }

         /* Output entire group list TODO make this an ajax search */
         $this->dropDown('all_group', $groupHandler->groupList(), '', TEMPLATE_content, array('id' => 'ALL_GROUP_ID' , 'name'=>'ALL_GROUP_NAME'));



        /* Get a list of permissions
		 */
        $permList = $groupHandler->getAllUserAccess($user['uid']);

        /* Clone-able permissions dropdown
		 * Remove existing options
		 */
        $this->dropDown('perm_campus', $campusList, 0, 'campusPermsList', array(
             'id' => 'PERM_CAMPUS_ID',
             'name' => 'PERM_CAMPUS_NAME'
        ));

        $this->parse('campusPermsList_block', 'campusPermsList');

        // arcane block magick for residential hice
        $this->dropDown(
            'perm_residential_house',
            $residentialHouseList,
            0,
            'residentialHousePermsList',
            array(
                'id'    => 'RESIDENT_HOUSE_ID',
                'house' => 'RESIDENT_HOUSE_NAME',
            )
        );
        $this->parse('residentialHousePermsList_block', 'residentialHousePermsList');


        if (is_array($permList)) {
            foreach($permList as $perm) {

                /* Radio boxes
				 */
                $this->setVar(array(
                    'SERVICE_ID'     => $perm['service'],
                    'READ_CHECKED'   => $perm['type'] == READ_ACCESS ? 'checked="checked"' : '',
                    'WRITE_CHECKED'  => $perm['type'] == WRITE_ACCESS ? 'checked="checked"' : '',
                    'ADMIN_CHECKED'  => $perm['type'] == FULL_ACCESS ? 'checked="checked"' : '',
                    'READ_SELECTED'  => $perm['type'] == READ_ACCESS ? 'selected' : '',
                    'WRITE_SELECTED' => $perm['type'] == WRITE_ACCESS ? 'selected' : '',
                    'ADMIN_SELECTED' => $perm['type'] == FULL_ACCESS ? 'selected' : '',
                    'SID'            => $perm['sid']
                ));

                /* Show identifier name
                 * Housemaster: sid = res house ID
                 * Calendar, News: sid = campus ID
                 * Others: not used
                 */
                switch ($perm['service']) {
                    case CALENDAR_SERVICE:
                    case NEWS_SERVICE:
                        foreach ($campusList as $campus) {
                            if ($campus['id'] == $perm['sid']) {
                                $this->setVar('PERM_CAMPUS_NAME', $campus['name']);
                                break;
                            }
                        }
                        break;

                    case HOUSEMASTER_SERVICE:
                        foreach ($residentialHouseList as $residentialHouse) {
                            if ($residentialHouse->getId() == $perm['sid']) {
                                $this->setVar('PERM_RESIDENT_HOUSE_NAME', $residentialHouse->getHouse());
                                break;
                            }
                        }
                        break;

                    case CMS_SERVICE:
                    case RESOURCE_BOOKING_SERVICE:
                    default:
                        break;
                }

                /* Render out service blocx
				 */
                switch($perm['service']) {

                    /* Calendar
					 */
                    case CALENDAR_SERVICE:
                        $this->parse('user_calendar_block', 'user_calendar', true);
                        break;

                    /* News
					 */
                    case NEWS_SERVICE:
                        $this->parse('user_news_block', 'user_news', true);
                        break;

                    /* CMS
					 */
                    case CMS_SERVICE:
                        $this->parse('user_cms_block', 'user_cms', true);
                        break;

                    /* Housemaster
					 */
                    case HOUSEMASTER_SERVICE:
                        $this->parse('user_housemaster_block', 'user_housemaster', true);
                        break;

                    /* Resource Booking
					 */
                    case RESOURCE_BOOKING_SERVICE:
                        $this->parse('user_resource_booking_block', 'user_resource_booking', true);
                        break;

                    /* Curriculum
                     * Use the constants for now, but they should be removed
                     */
                    case CURRICULUM_SERVICE:
                        $this->parse('user_curriculum_block', 'user_curriculum', true);
                        break;
                }

                /* Clear child blocks after parsing out parent
				 */
                $this->setVar('perm_campus_block', '');
            }
        }

         /* Output login log */
        $this->setBlock(TEMPLATE_content, 'login_log', 'login_log_block');
        $loginLog = $userHandler->getUserLogins($user['username']);
        if(is_array($loginLog)) {
            foreach($loginLog as $result) {
                $loginDate = new Alaress_Date_Format(strtotime($result['date']), 'U');
                $output    = array (
                    'LOGIN_DATE'   => $loginDate->getLocal(),
                    'LOGIN_IP'     => $result['ip'],
                    'LOGIN_RESULT' => $result['login']?'Success':'Failed',
                );
                $this->setVar($output);
                $this->parse('login_log_block', 'login_log', true);
            }
        } else
            $this->setVar('login_log_block', '');

        /* Output linked accounts */
        $this->setBlock(TEMPLATE_content, 'user_linked', 'user_linked_block');
        $this->setVar('user_linked_block', '');
        foreach($userObj->getLinkedUser() as $linkedUser){
            /** @param \Schoolbox\User $linkedUser */
            $this->setVar(array('LINKED_USER_ID'  => $linkedUser->getId(),
                            'LINKED_USER'   => $linkedUser->getFullName(),
                            'LINKED_USER_DATE' => '',));
            $this->parse('user_linked_block', 'user_linked',true);
        }

         /* Output activity log */
         $this->setBlock(TEMPLATE_content, 'action_log', 'action_log_block');
         $activityLog = $userHandler->getUserActivity($user['uid'],10);
         $lookupAction = array('create'=>'Created',
                                'modify'=>'Modified',
                                'delete'=>'Deleted');

        if(is_array($activityLog)) {
            foreach($activityLog as $result) {
                $date   = new Alaress_Date_Format(strtotime($result['time']), 'U');
                $output = array (
                    'ACTION_DATE'   => $date->getLocal(),
                    'ACTION_ACTION' => $lookupAction[$result['action']].' '.$result['object'].' '.$result[$result['object']],
                );
                $this->setVar($output);
                $this->parse('action_log_block', 'action_log', true);
            }
        } else
                $this->setVar('action_log_block', '');

    }
} else {
    $this->setFile(TEMPLATE_content, 'adminv2/new-user.html');
    $this->setTitle('Admin - New User');

    // include the admin navigation
    $nav = new AdminV2_Nav();
    $nav->parse($this, 'user');

    $this->setVar('DATABASE_TYPE', ucfirst(Cfg::get('external_type')));

    /* Output title list */
    $this->dropDown('title', $userHandler->getTitleList(),'', TEMPLATE_content, array('titleName'=>'NAME_TITLE'));

    /* Output group list */
     $this->dropDown('system_group', $userHandler->getGroupList(), '', TEMPLATE_content, array('id'=>'GROUP_ID','name'=>'SYSTEM_GROUP_NAME'));

    /* Output house list */
    $this->dropDown('house', HouseMapper::getInstance()->findAll(), $user['house'], TEMPLATE_content, array('id'=>'HOUSE_ID','house'=>'HOUSE_NAME'));
    $this->dropDown('residential_house', HouseResidentialMapper::getInstance()->findAll(), $user['residential_house'], TEMPLATE_content, array('id' => 'RESIDENT_HOUSE_ID', 'house' => 'RESIDENT_HOUSE_NAME'));


    /* Output campus list */
     $this->setBlock(TEMPLATE_content, 'campus','campus_block');
     $campusList = $userHandler->getCampusList();
     foreach($campusList as $campus ) {

         $outcampus = array('CAMPUS_ID'         => $campus['id'],
                        'CAMPUS_NAME'           => Scrub::text($campus['name']),
                        'CAMPUS_ID_CHECKED'     => '',
                        'CAMPUS_DEFAULT_CHECKED'    => '',);

         $this->setVar($outcampus);
         $this->parse('campus_block','campus',true);
     }
}
