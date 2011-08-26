<?php
class OrmondLDAP {
	private $ldapDomain = "ldap://francis2.ormond.unimelb.edu.au";

	public function getLDAPdn($username) {
		$ds = @ldap_connect($this->ldapDomain);
	#        @ldap_bind($ds, "cn=nss,dc=ormond,dc=unimelb,dc=edu,dc=au", "b9723raw^k");
		ldap_bind($ds, "cn=admin,dc=ormond,dc=unimelb,dc=edu,dc=au", "Bags0$");

		$result = @ldap_search($ds, "dc=ormond,dc=unimelb,dc=edu,dc=au","uid=".$username,array("dn"));
		$entries = @ldap_get_entries($ds, $result);
		$dn = $entries[0]['dn'];

        if($dn) {
			return $dn;
        }
		else {
			return false;
        }
	}

	public function getLDAPemail($username, $userdn) {
		$ds = ldap_connect($this->ldapDomain);
	#        $bind = @ldap_bind($ds, "cn=nss,dc=ormond,dc=unimelb,dc=edu,dc=au", "b9723raw^k");
		$bind = ldap_bind($ds, "cn=admin,dc=ormond,dc=unimelb,dc=edu,dc=au", "Bags0$");

		$search_array = array("mailRoutingAddress");
		$result = ldap_search($ds, $userdn, "uid=".$username, $search_array);
		$entry = ldap_first_entry($ds, $result);
		$info = ldap_get_values($ds, $entry, "mailRoutingAddress");

		return $info[0];
	}
}