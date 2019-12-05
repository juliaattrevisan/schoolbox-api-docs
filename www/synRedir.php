<?php
/* Script to automatically login to synergetic portal based on existing login creditials */
require_once __DIR__ . '/../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();
$page = new SitePage;
$user = $_SESSION['user'];
$url = new \Zend\Uri\Uri(Config::get('external_portal'));

/* If the user has already passed by the login, we can go directly to the page */
if($_SESSION['communityLogin']) {
	if($_GET['page']){
		$url->setPath('/'.$_GET['page'].'.aspx');
	} else {
		// This seems to be the default post-login landing page
		$url->setPath('/stures.aspx');
	}

	$query = $url->getQueryAsArray();
	if ($_GET['id']) {
		$query['id'] = $_GET['id'];
	}
	if ($_GET['sn']) {
		$query['sn'] = $_GET['sn'];
	}

	/* If no menu requested turn it off */
	if ($_GET['menu']) {
		$query['menu'] = 0;
	}
	$url->setQuery($query);

	header('Location: '.$url->toString());
	exit;
}
else {
	/* Setup form variable */
	$url->setPath('/login.aspx');
	$form   = "Form1";
	$action = $url->toString();

	/* First contact the site to get some CSRF hacks */
	$method="POST";
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $action);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_USERAGENT, 'schoolbox/'.Config::get('site_version'));
	$login = curl_exec($ch);
	curl_close($ch);
	unset($ch);

	/* Set the URL to redirect the user to after login */
	if ($_GET['page']) {
		if ($_GET['menu']) {
			$action = $action . '?ReturnUrl=' . urlencode($_GET['page'] . '.aspx?menu=0');
		} else {
			$action = $action . '?ReturnUrl=' . $_GET['page'] . '.aspx';
		}
	}

	preg_match('/id="__VIEWSTATE" value="(.*?)"/', $login, $viewState);
	preg_match('/id="__EVENTVALIDATION" value="(.*?)"/', $login, $eventValidation);
	preg_match('/name="(.*?)" value="Login"/', $login, $loginButton);
	preg_match('/name="(.*?)" type="text"/', $login, $loginUsername);
	preg_match('/name="(.*?)" type="password"/', $login, $loginPassword);

	$password = $_SESSION['password'];
	$formArray['__VIEWSTATE'] = $viewState[1];
	$formArray['__EVENTVALIDATION'] = $eventValidation[1];
	$formArray['__EVENTARGUMENT'] = '';
	$formArray['__EVENTTARGET'] = '';
	$formArray['__LASTFOCUS'] = '';
	$formArray[$loginButton[1]] = 'Login';
	$formArray[$loginUsername[1]] = $user->getUsername();
	$formArray[$loginPassword[1]] = $password;
	$formArray['loginButton'] = 'Login';
	$formArray['textBox1'] = $user->getUsername();
	$formArray['textBox2'] = $password;

	$_SESSION['communityLogin'] = true;
}
?>

<html>
<head>
<title>Redirecting to <?php echo $to ?></title>

</head>

<?php
echo "<body>";

echo"<form method='{$method}' name='{$form}' action='{$action}'>";
if(is_array($formArray)) {
	foreach($formArray as $key => $value)
	{	
		if($key == "submit")
			{
			echo "<br><br><br><br><br><br><br><br><div align='center'>Please $value to continue<br>";
			echo "<input name='$key' type='submit' value='$value'></div>";
			$nojs = 1;
			}
		else	
			echo "<input name='$key' type='hidden' value='$value'>";
	}
}
?>
</form>
<script>
<?php
if (!($nojs))
	echo "document.{$form}.submit();";
?>
</script>
</body>
</html>
