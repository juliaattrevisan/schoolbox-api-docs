<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/Bootstrap.php';
$page = new SitePage;


//A Semi-Smart Autologon Script 
//It remains to be seen who falls for it
//010405, time to pull an April Fools!!!
//lwcawood


if($_GET)
{

$to = $_GET['directTo'];
switch($to)
{

    case "worldbook":
        $form="loginForm";
        $action="http://www.worldbookonline.com/wb/products?ed=all";
        $method="POST";
        $formArray['subacct']='AU04114';
        $formArray['uid']="melbggs";
        $formArray['pwd']="melbourne";
        $formArray['auth']="UidPassword";

    break;

    case "worldbook-kids":
        $form="loginForm";
        $action="http://www.worldbookonline.com/wb/products?ed=all";
        $method="POST";
        $formArray['subacct']='AU04114';
        $formArray['uid']="melbggs";
        $formArray['pwd']="melbourne";
        $formArray['auth']="UidPassword";
        $formArray['tu']='http://worldbookonline.com/kids/home';
    break;


    case "britannica":
        $form="loginForm";
        $action="http://school.eb.com.au/login";
        $method="POST";
        $formArray['username']="mggs";
        $formArray['password']="mggs";
        $formArray['target'] = '/';
    break;

    case "britannica-kids":
        $form="loginForm";
        $action="https://secure.britannica.com/login";
        $method="POST";
        $formArray['username']="mggs";
        $formArray['password']="mggs";
        $formArray['remember'] = 'false';
        $formArray['fail']	= 'http://kids.britannica.com/failedlogin';
        $formArray['target'] = 'http://kids.britannica.com/';
    break;

    case "oxford":
            $form="form";
            $action="http://www.oxfordreference.com/AUTHENTICATE";
            $method="POST";
            $formArray['scope'] = 'global';
            $formArray['username'] = '';
            $formArray['password'] = '';
            $formArray['librarycard'] = 'mggs12345';
    break;

    case "librarywebs";
        $form = "Login";
        $action = "http://online.librarywebs.net/Login.aspx";
        $method = "post";
        $formArray = array('inputUsername' => 'mggs',
                   'inputPassword' => 'mggs05',
                   '__VIEWSTATE' => '/wEPDwUKMTQ4NzAxMjMzMA9kFgICAQ9kFgQCAQ9kFgICBw8PFgIeB1Zpc2libGVnZGQCAw8PFgIfAGhkZBgBBR5fX0NvbnRyb2xzUmVxdWlyZVBvc3RCYWNrS2V5X18WAQUIYnRuTG9naW4VXANH39VlWJdsuQPLrmub5mrDNg==',
                  'btnLogin'	=> '',
                );

    break;

    case "active-history":
        $form="form";
        $action="http://www.activehistory.co.uk/amember/plugins/protect/new_rewrite/login.php?v=-1,2,6,7,8,9,10,11,12,13,14&url=/main_area/index.php";
        $method="POST";
	    $formArray['amember_login'] = 'melbgirls';
        $formArray['amember_pass'] = 'mggs1893';
        //$formArray['login_attempt_id'] = '1312519491';
    break;

    case "magpies":
        $form="form";
        $action="https://www.magpies.net.au/wp-login.php";
        $method="POST";
	    $formArray['log'] = 'mggs';
        $formArray['pwd'] = 'girls';
        $formArray['redirect_to'] = "https://www.magpies.net.au/the-source/";
        break;


    case "ebsco":
        $form="Form1";
        $action="http://search.ebscohost.com/login.aspx";
        $method="POST";
        $formArray['__VIEWSTATE'] ="/wEPDwUJMjEzNzg1OTE1ZGQ=";
        $formArray['FormSubmit'] = 'true';
        $formArray['user'] = 'mggs';
        $formArray['password'] = "grammar11";
        $formArray['btnLogin'] = "Login";
        $formArray['authtype'] = "uid";
        $formArray['ref'] = "";
    break;

    case "infotrac":
        $form="form";
        $action="http://infotrac.galegroup.com/itweb/melgg";
        $method="GET";
        $formArray['id'] = 'literature';
    break;

default:
	echo "Did not recognise the site";
break;

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
<?php
}
?>
