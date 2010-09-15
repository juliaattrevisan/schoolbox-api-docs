<?
/* Script to automatically login to synergetic portal based on existing login creditials */
require_once($_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php');
require_once('../library/user.php');
require_once('../library/group.php');
$page = new SitePage;
$userHandler = new User;
$groupHandler = new Group;

$user = $userHandler->getByID($_SESSION['id']);

if($groupHandler->isParent()) {
    /* Setup form variable */
    $form="Form1";
    $action=SYNERGY_portal.'login.aspx';
    $method="POST";

    $formArray['__VIEWSTATE'] ="/wEPDwUKMTg3NzM1MTc4NQ9kFgJmD2QWAgIDD2QWBgIBDxYCHgdWaXNpYmxlaGQCAw8WAh8AaGQCBQ9kFgICAQ9kFgQCAw8PFgIeBFRleHRlZGQCBQ9kFgICFw8PFgIfAWVkZGQRTaP46MtESfYUfqxQrRkv5ADi9g==";
    $formArray['__EVENTVALIDATION'] = '/wEWBALyk7P0CwLLuNPhBAKwz7XMCgKO0+OzAvC6Tnply1JVZvuxB983AhxejoYz';
    $formArray['__EVENTARGUMENT'] = '';
    $formArray['__EVENTTARGET'] = '';
    $formArray['__LASTFOCUS'] = '';
    $formArray['ctl00$masterPageContentPlaceHolder$loginButton'] = 'Login';
    $formArray['ctl00$masterPageContentPlaceHolder$textBox1'] = $user['username'];
    $formArray['ctl00$masterPageContentPlaceHolder$textBox2'] = $user['email_pass'];
}
else {
    exit;
}


?>

<html>
<head>
<title>Redirecting to <? echo $to ?></title>

</head>

<?
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
<?
if (!($nojs))
	echo "document.{$form}.submit();";
?>
</script>
</body>
</html>