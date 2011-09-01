<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/include/session.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/conf/conf.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/include/stdfuncs.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/include/SitePage.php');

include ('./createlm.php');


# Checks which DN the user belongs to
function getLDAPdn ($username) {
	$ds=@ldap_connect("ldap://francis2.ormond.unimelb.edu.au");
#	@ldap_bind($ds, "cn=nss,dc=ormond,dc=unimelb,dc=edu,dc=au", "b9723raw^k");
	@ldap_bind($ds, "cn=admin,dc=ormond,dc=unimelb,dc=edu,dc=au", "Bags0$");
	$result = @ldap_search($ds, "dc=ormond,dc=unimelb,dc=edu,dc=au","uid=".$username,array("dn"));
	$entries = @ldap_get_entries($ds, $result);
	$dn = $entries[0]['dn'];
	if($dn) {
		return $dn;
        } else {
		return false;
        }
}


# Check if has a valid session, otherwise, take to login page
session_start();
if ( !isset($_SESSION['id']) ) {
	$page = new SitePage;
	$page->goLoginPage();
}

# Get username based on userID
mysql_connect(localhost,webgui,PainA22);
@mysql_select_db(intranet) or die( "Unable to select database");
$query = "SELECT username FROM user WHERE id = '".$_POST['uid']."'";
$result = mysql_query($query);
$uname = mysql_result($result,0,'username');



$oldpassword1 = $_POST["old1"];
$newpassword1 = $_POST["new1"];
$newpassword2 = $_POST["new2"];

print $oldpassword1;

if ($newpassword1 == $newpassword2) {

   $smb = new smbHash;

   $passwordarray["lmPassword"] = $smb->lmhash($newpassword1);
   $passwordarray["ntPassword"] = $smb->nthash($newpassword1);
   $passwordarray["userPassword"] = crypt($newpassword1);

   $dn = getLDAPdn($uname);
   $ldapconn = ldap_connect("francis2.ormond.unimelb.edu.au", 389);
   $ldapbind = @ldap_bind($ldapconn,$dn,$oldpassword1);

   if($ldapbind) {
      print "<p>Changing password ...";
      if(ldap_mod_replace ($ldapconn, $dn, $passwordarray)) {
         print " success";
      } else {
         print " failed!";
      }
   } else {
      print "Connection failed, please retry your password";
   }

} else {

   print "New passwords do not match, please re-enter";

}

?>
