<?php

require_once($_SERVER['DOCUMENT_ROOT']."/include/session.php");
require_once($_SERVER['DOCUMENT_ROOT']."/include/include.php");

class EmailForward {

function EmailForward() {
        $this->db = new dbManager();
}

function displayForm() {

        $this->t_body = new Template(TPL_directory);

        $this->t_body->set_file("tplBODY", "modules/emailforward/getForward.htm");
        $this->t_body->set_var("TITLE", HEADING." - Change Email Forward");

        include($_SERVER['DOCUMENT_ROOT'].'/include/page.php');

        $user = $db->dbGetSingleRow("SELECT username FROM user WHERE id = {$_SESSION['id']}");

        $this->t_body->set_var('UID', $_SESSION['uid']);
        $this->t_body->set_var('USERNAME', $user['username']);
        $userdn = $this->getLDAPdn($user['username']);
        $mailaddress = $this->getLDAPemail($user['username'], $userdn);
        $this->t_body->set_var('EMAIL', $mailaddress);    

        $this->t_body->pparse('finalout','tplBODY');


}

function displayConfirmation($message, $emailad, $password, $uname) {
        $this->t_body = new Template(TPL_directory);

        $this->t_body->set_file("tplBODY", "modules/emailforward/confirm.htm");
        $this->t_body->set_var("TITLE", HEADING." - Change Email Forward Confirmation");

        include($_SERVER['DOCUMENT_ROOT'].'/include/page.php');

        if ($message != "") {
           $this->t_body->set_var('MESSAGE', $message);
        } else {
           $dn = $this->getLDAPdn($uname);
           $ldapconn = ldap_connect("francis2.ormond.unimelb.edu.au", 389);
           $ldapbind = @ldap_bind($ldapconn,$dn,$password);
           
           $emailarray["mailRoutingAddress"] = $emailad;

           if($ldapbind) {
	      if(ldap_mod_replace ($ldapconn, $dn, $emailarray)) {
	         $message = 'Email Forward Successful';
	      } else {
	         $message = 'Email Update Failed';
	      }
	   } else {
	         $message = 'Connection failed, please retry your password';
	   }
           $this->t_body->set_var('MESSAGE', $message);
	}

        $this->t_body->pparse('finalout','tplBODY');
}

function getLDAPdn ($username) {
        $ds=@ldap_connect("ldap://francis2.ormond.unimelb.edu.au");
#        @ldap_bind($ds, "cn=nss,dc=ormond,dc=unimelb,dc=edu,dc=au", "b9723raw^k");
        ldap_bind($ds, "cn=admin,dc=ormond,dc=unimelb,dc=edu,dc=au", "Bags0$");
        $result = @ldap_search($ds, "dc=ormond,dc=unimelb,dc=edu,dc=au","uid=".$username,array("dn"));
        $entries = @ldap_get_entries($ds, $result);
        $dn = $entries[0]['dn'];
        if($dn) {
                return $dn;
        } else {
                return false;
        }
}

function getLDAPemail ($username, $userdn) {
        $ds=ldap_connect("ldap://francis2.ormond.unimelb.edu.au");
#        $bind = @ldap_bind($ds, "cn=nss,dc=ormond,dc=unimelb,dc=edu,dc=au", "b9723raw^k");
        $bind = ldap_bind($ds, "cn=admin,dc=ormond,dc=unimelb,dc=edu,dc=au", "Bags0$");
        $search_array = array("mailRoutingAddress");
        $result = ldap_search($ds, $userdn, "uid=".$username, $search_array);
        $entry = ldap_first_entry($ds, $result);
	$info = ldap_get_values($ds, $entry, "mailRoutingAddress");
        return $info[0];
}

}


$page = new EmailForward;

if($_POST['emailad'] && $_POST['password']) {
   $page->displayConfirmation("", $_POST['emailad'], $_POST['password'], $_POST['uname']);
} elseif ($_POST['emailad']) {
   $page->displayConfirmation("You did not enter a password, please resubmit with your current Ormond password.", "", "", "");
} elseif ($_POST['password']) {
   $page->displayConfirmation("You did not enter a forwarding address, please resubmit.", "", "", "");
} else {
   $page->displayForm();
}


?>
