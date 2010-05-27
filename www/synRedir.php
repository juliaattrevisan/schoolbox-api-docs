<?
/* Script to automatically login to synergetic portal based on existing login creditials */
require_once($_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php');
require_once('../library/user.php');
require_once('../library/group.php');
$page = new SitePage;
$userHandler = new User;
$groupHandler = new Group;

$user = $userHandler->getByID($_SESSION['id']);

if($this->groupHandler->isParent()) {
    /* Setup form variable */
    $form="Form1";
    $action=SYNERGY_parent_portal.'/login.aspx';
    $method="POST";

    $formArray['__VIEWSTATE'] ="/wEPDwUJNDQ4ODI3NTE3D2QWAgIBD2QWBgIBDxYCHgRUZXh0BUk8dGFibGUgY2xhc3M9J2hkcl90YmxfY29udGFpbmVyJyBjZWxsc3BhY2luZz0nMCcgY2VsbHBhZGRpbmc9JzAnPjwvdGFibGU+ZAIFDw8WAh8AZWRkAh0PDxYCHwBlZGRkpB4p8bXZwfqTt5wApp33zajoNN4=";
    $formArray['__EVENTVALIDATION'] = '/wEWBALN9c/RCgLMyrLrBgLMyvbZDALejM6fD9VrrUoKDYo3oJtoRbUM56XHdURL';
    $formArray['loginButton'] = 'true';
    $formArray['textBox1'] = $user['username'];
    $formArray['textBox2'] = $user['email_pass'];
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