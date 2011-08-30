<?php

require_once '../include/stdfuncs.php';
require_once '../../library/user.php';

class schoolbox_addressbook_backend extends rcube_addressbook {

    public $primary_key = 'ID';
    public $readonly = true;
    public $groups = false;
    private $filter;
    private $result;

    public function __construct() {
        $this->ready = true;
        $this->user = new User();
    }

    public function set_search_set($filter) {
        $this->filter = $filter;
    }

    public function get_search_set() {
        return $this->filter;
    }

    public function reset() {
        $this->result = null;
        $this->filter = null;
    }

    public function count() {
        return new rcube_result_set($this->user_count, ($this->list_page - 1) * $this->page_size);
    }

    public function get_result() {
        return $this->result;
    }

    public function list_records($cols=null, $subset=0) {
		$userHandler = new User();

        $users = $this->user->search('', ($this->list_page - 1) * $this->page_size, $this->list_page * $this->page_size, $count);

        $this->user_count = $count;
        $this->result = $this->count();

		if(is_array($users)) {
        	foreach ($users as $user) {
				$emailObj = new Email();
				$email = $emailObj->formatEmailFromArray($user);

        	    $this->result->add(array(
        	        'ID'        => $user['id'],
        	        'name'      => $userHandler->formatName($user),
        	        'firstname' => $user['firstname'],
        	        'surname'   => $user['lastname'],
        	        'email'     => $email
        	    ));
        	}
	}

        return $this->result;
    }

    public function search($fields, $value, $strict=false, $select=true, $nocount=false, $required=array()) {
		$groups = array_merge(unserialize(STUDENTGROUPIDS),unserialize(STAFFGROUPIDS));
        $users = $this->user->ajaxSearch($value, $groups, 20);

        $this->user_count = $count;
        $this->result = $this->count();

		if(is_array($users) ) {
			$userHandler = new User();

        	foreach ($users as $user) {
				$emailObj = new Email();
				$email = $emailObj->formatEmailFromArray($user);

        	    $this->result->add(array(
        	        'ID'        => $user['id'],
        	        'name'      => $userHandler->formatName($user),
        	        'firstname' => $user['firstname'],
        	        'surname'   => $user['lastname'],
        	        'email'     => $email,
        	    ));
        	}
	}

        return $this->result;
    }

    public function get_record($id, $assoc=false) {
        $users = $this->user->getAllByField('id', $id); // really just one user

        $this->user_count = $count;
        $this->result = $this->count();

		$userHandler = new User();

        foreach ($users as $user) {
			$emailObj = new Email();
			$email = $emailObj->formatEmailFromArray($user);

            $this->result->add(array(
                'ID'        => $user['id'],
                'name'      => $userHandler->formatName($user),
                'firstname' => $user['firstname'],
                'surname'   => $user['lastname'],
                'email'     => $email,
            ));
        }

        return $assoc && $users ? $users : $this->result;
    }

    function list_groups($search = null) {
        return false;
    }

    function create_group($name) {
        return false;
    }

    function delete_group($gid) {
        return false;
    }

    function rename_group($gid, $newname) {
        return false;
    }

    function add_to_group($group_id, $ids) {
        return false;
    }

    function remove_from_group($group_id, $ids) {
        return false;
    }

}
