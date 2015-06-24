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

//DM 2012-04-02
case "twigit":
//        $form="form";
//        $action="http://twig-it.com/login.php";
//        $method="POST";
//	 $formArray['username'] = "library";
//        $formArray['passwd'] = "twigbialik";
//        $formArray['option'] = "com_user";
//        $formArray['task'] = "login";
//       $formArray['return'] = "L2xvZ2luLnBocD9tbD05OQ==";
//       $formArray['27d42a8b93666cd17c65b7479d9ddcf7'] = "";

        $form="form";
        $action="http://www.twig-it.com";
        $method="GET";


break;


//DM 2012-04-02
case "newsbank":
      $form="form";
//      $action="http://infoweb.newsbank.com/cgi-bin/myremote.pl";
      $action="http://infoweb.newsbank.com/cgi-bin/remote/";
      $method="GET";
break;






//DM 2012-04-02
case "infotrac-GVRL":
        $form="form";
        $action="http://infotrac.galegroup.com/itweb/61bialik";
        $method="GET";
	$formArray['db'] = 'gvrl';
break;


//DM 2012-04-02
case "infotrac-WHIC":
        $form="form";
        $action="http://infotrac.galegroup.com/itweb/61bialik";
        $method="GET";
	$formArray['db'] = 'whic';
break;


//DM 2012-04-02
case "infotrac-SCIC":
        $form="form";
        $action="http://infotrac.galegroup.com/itweb/61bialik";
        $method="GET";
	$formArray['db'] = 'scic';
break;





//DM 2012-04-01
case "ebsco":
        $form="Form1";
        $action="http://search.ebscohost.com/login.aspx";
        $method="POST";
        $formArray['__VIEWSTATE'] ="/wEPDwUJMjEzNzg1OTE1ZGQ=";
        $formArray['FormSubmit'] = 'true';
        $formArray['user'] = 'bialik';
        $formArray['password'] = "hawthorn";
        $formArray['btnLogin'] = "Login";
        $formArray['authtype'] = "uid";
        $formArray['ref'] = "";
break;





//DM 2012-04-01
case "ebsco-anh":
        $form="Form1";
        $action="http://search.ebscohost.com/login.aspx?profile=ehost&defaultdb=anh";
        $method="POST";
        $formArray['__VIEWSTATE'] ="/wEPDwUJMjEzNzg1OTE1ZGQ=";
        $formArray['FormSubmit'] = 'true';
        $formArray['user'] = 'bialik';
        $formArray['password'] = "hawthorn";
        $formArray['btnLogin'] = "Login";
        $formArray['authtype'] = "uid";
        $formArray['ref'] = "";
break;


//DM 2012-04-01
case "ebsco-nlebk":
        $form="Form1";
        $action="http://search.ebscohost.com/login.aspx?profile=ehost&defaultdb=nlebk";
        $method="POST";
        $formArray['__VIEWSTATE'] ="/wEPDwUJMjEzNzg1OTE1ZGQ=";
        $formArray['FormSubmit'] = 'true';
        $formArray['user'] = 'bialik';
        $formArray['password'] = "hawthorn";
        $formArray['btnLogin'] = "Login";
        $formArray['authtype'] = "uid";
        $formArray['ref'] = "";
break;




//DM 2012-04-01
case "jstor":
	$form="frmLogin";
	$action="http://www.jstor.org/action/doLogin";
	$method="POST";
	$formArray['redirectUri']="/action/showBasicSearch";
	$formArray['loginUri']="/action/showLogin?redirectUri=%2Faction%2FshowBasicSearch";
	$formArray['login']="bialikschool";
	$formArray['password']="429hawthorn";
break;


//DM 2012-04-02
case "jstor-plants":
	$form="frmLogin";
	$action="http://plants.jstor.org/action/doLogin";
	$method="POST";
//	$formArray['redirectUri']="/action/showBasicSearch";
	$formArray['loginUri']="/action/showLogin?redirectUri=%2Faction%2FshowBasicSearch";
	$formArray['login']="bialikschool";
	$formArray['password']="429hawthorn";
break;



//DM 2012-04-02
case "infomit-tvnews":
	$form="loginForm";
	$action="http://search.informit.com.au/search;res%3DTVNEWS";
	$method="POST";
	$formArray['username']="bialik";
	$formArray['password']="18589";
break;




default:
	echo "Did not recognise the site";
break;

}






?>

<html>
<head>
<title>Redirecting todd <? echo $to ?></title>

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
