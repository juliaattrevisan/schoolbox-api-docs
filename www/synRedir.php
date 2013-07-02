<?php
/* Script to automatically login to synergetic portal based on existing login creditials */
require_once($_SERVER['DOCUMENT_ROOT'] . '/../include/SitePage.php');
require_once('../library/user.php');
require_once('../library/group.php');
$page = new SitePage;
$userHandler = new User;
$groupHandler = new Group;

$user = $userHandler->getByID($_SESSION['id']);

/* Setup form variable */
$form="Form1";
$action=Cfg::get('external_portal').'login.aspx';
$method="POST";

$login = file_get_contents($action);

preg_match('/id="__VIEWSTATE" value="(.*?)"/', $login, $viewState);
preg_match('/id="__EVENTVALIDATION" value="(.*?)"/', $login, $eventValidation);

$password = isset($_SESSION['password']) ? $_SESSION['password'] : $user['email_pass'];
$formArray['__VIEWSTATE'] = $viewState[1];
$formArray['__EVENTVALIDATION'] = $eventValidation[1];
$formArray['__EVENTARGUMENT'] = '';
$formArray['__EVENTTARGET'] = '';
$formArray['__LASTFOCUS'] = '';
$formArray['ctl00$masterPageContentPlaceHolder$loginButton'] = 'Login';
$formArray['ctl00$masterPageContentPlaceHolder$textBox1'] = $user['username'];
$formArray['ctl00$masterPageContentPlaceHolder$textBox2'] = $password;
$formArray['loginButton'] = 'Login';
$formArray['textBox1'] = $user['username'];
$formArray['textBox2'] = $password;

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
