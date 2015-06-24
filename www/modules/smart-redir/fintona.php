<?php

//A Semi-Smart Autologon Script 
//It remains to be seen who falls for it
//010405, time to pull an April Fools!!!
//lwcawood


if($_GET)
{

$to = $_GET['directTo'];
switch($to)
{

    case "activehistory" :
        $form = "form";
        $action = "http://www.activehistory.co.uk/amember/login.php";
        $method="POST";
        $formArray['amember_login'] = 'hist';
        $formArray['amember_pass'] = 'wool';
    break;


    case "theage" :
        $form ="form";
        $action = "http://www.libraryedition.com.au/truetoprint/fnc_login.php";
        $method = "POST";
        $formArray['accountID'] = '3108';
        $formArray['password'] = 'wolf919';
        $formArray['btnLogin2'] = 'Login';
    break;

    case "ebsco":
        $form="Form1";
        $action="http://search.ebscohost.com/login.aspx";
        $method="POST";
        $formArray['__VIEWSTATE'] ="/wEPDwUJMjEzNzg1OTE1ZGQ=";
        $formArray['FormSubmit'] = 'true';
        $formArray['user'] = 'fintona';
        $formArray['password'] = "chilvers";
        $formArray['btnLogin'] = "Login";
        $formArray['authtype'] = "uid";
        $formArray['ref'] = "";
        break;

    case "ebsco-bib":
        $form="Form1";
        $action="http://search.ebscohost.com/login.aspx?profile=brc";
        $method="POST";
        $formArray['__VIEWSTATE'] ="/wEPDwUJMjEzNzg1OTE1ZGQ=";
        $formArray['FormSubmit'] = 'true';
        $formArray['user'] = 'fintona';
        $formArray['password'] = "chilvers";
        $formArray['btnLogin'] = "Login";
        $formArray['authtype'] = "uid";
        $formArray['ref'] = "";
        break;

    case "ebsco-history":
        $form="Form1";
        $action="http://search.ebscohost.com/login.aspx?profile=ehost&defaultdb=khh";
        $method="POST";
        $formArray['__VIEWSTATE'] ="/wEPDwUJMjEzNzg1OTE1ZGQ=";
        $formArray['FormSubmit'] = 'true';
        $formArray['user'] = 'fintona';
        $formArray['password'] = "chilvers";
        $formArray['btnLogin'] = "Login";
        $formArray['authtype'] = "uid";
        $formArray['ref'] = "";
        break;

    case "ebsco-bib":
        $form="Form1";
        $action="http://search.ebscohost.com/login.aspx?profile=brc";
        $method="POST";
        $formArray['__VIEWSTATE'] ="/wEPDwUJMjEzNzg1OTE1ZGQ=";
        $formArray['FormSubmit'] = 'true';
        $formArray['user'] = 'fintona';
        $formArray['password'] = "chilvers";
        $formArray['btnLogin'] = "Login";
        $formArray['authtype'] = "uid";
        $formArray['ref'] = "";
        break;

    case "ebsco-pov":
        $form="Form1";
        $action="http://search.ebscohost.com/login.aspx?site=pov";
        $method="POST";
        $formArray['__VIEWSTATE'] ="/wEPDwUJMjEzNzg1OTE1ZGQ=";
        $formArray['FormSubmit'] = 'true';
        $formArray['user'] = 'fintona';
        $formArray['password'] = "chilvers";
        $formArray['btnLogin'] = "Login";
        $formArray['authtype'] = "uid";
        $formArray['ref'] = "";
        break;

    case "ebsco-anh":
        $form="Form1";
        $action="http://search.ebscohost.com/login.aspx?profile=ehost&defaultdb=anh";
        $method="POST";
        $formArray['__VIEWSTATE'] ="/wEPDwUJMjEzNzg1OTE1ZGQ=";
        $formArray['FormSubmit'] = 'true';
        $formArray['user'] = 'fintona';
        $formArray['password'] = "chilvers";
        $formArray['btnLogin'] = "Login";
        $formArray['authtype'] = "uid";
        $formArray['ref'] = "";
        break;

    case "ebsco-sch":
        $form="Form1";
        $action="http://search.ebscohost.com/login.aspx?profile=ehost&defaultdb=sch";
        $method="POST";
        $formArray['__VIEWSTATE'] ="/wEPDwUJMjEzNzg1OTE1ZGQ=";
        $formArray['FormSubmit'] = 'true';
        $formArray['user'] = 'fintona';
        $formArray['password'] = "chilvers";
        $formArray['btnLogin'] = "Login";
        $formArray['authtype'] = "uid";
        $formArray['ref'] = "";
        break;


    case "ebsco-lrc":
        $form="Form1";
        $action="http://search.ebscohost.com/login.aspx?profile=lrc";
        $method="POST";
        $formArray['__VIEWSTATE'] ="/wEPDwUJMjEzNzg1OTE1ZGQ=";
        $formArray['FormSubmit'] = 'true';
        $formArray['user'] = 'fintona';
        $formArray['password'] = "chilvers";
        $formArray['btnLogin'] = "Login";
        $formArray['authtype'] = "uid";
        $formArray['ref'] = "";
        break;

    case "echalk" :
        $form="form";
        $action = "http://subscription.echalk.co.uk/login/index.aspx";
        $method="POST";
        $formArray['strUsername'] = 'fintona';
        $formArray['strPassword'] = 'noblePupil';
        $formArray['Submit'] = 'Login';
    break;

    case 'enchantedlearning' :
        $form = "form";
        $action = "http://members.enchangedlearning.com/cgi-bin/uncgi/login";
        $method="POST";
        $formArray['_username'] = '';
        $formArray['_pw'] = '';
        $formArray['Submit'] = 'Login';
    break;


    case "readplus":
        $form="form";
        $action="http://www.readplus.com.au/loginck.php";
        $method="GET";
        $formArray['userid'] = 'fintona';
        $formArray['password'] = 'school';
        break;

    case "sunshine":
        $form="form";
        $action="http://www.sunshineonline.com.au/login.php";
        $method="POST";
        $formArray['user'] = 'fintonagirls';
        $formArray['pass'] = 'fintona';
        break;

    case "gtav":
        $form="form";
        $action="http://www.gtav.asn.au/index.php";
        $method="POST";
        $formArray['loginformused'] = '1';
        $formArray['username'] = 'mgilbert@fintona.vic.edu.au';
        $formArray['password'] = 'rcbj37';
        break;

    case "htav":
        $form="form";
        $action="http://www.htav.asn.au/member-centre";
        $method="POST";
        $formArray['command'] = 'login';
        $formArray['loginusername'] = 'SueWoolley';
        $formArray['loginpassword'] = 'Victoria';
        break;

    case "petaa":
        $form="form";
        $action="http://www.petaa.edu.au/Login";
        $method="POST";
        $formArray['command'] = 'login';
        $formArray['ctl00$ContentPlaceHolder$ctl00$txtUsername'] = '100916';
        $formArray['ctl00$ContentPlaceHolder$ctl00$txtPassword'] = '100916@petaa';
        break;

    case "ceramics":
        $form="form";
        $action="http://australianceramics.realviewdigital.com/login.secure";
        $method="GET";
        $formArray['user'] = 'fgs@fintona.vic.edu.au';
        $formArray['password'] = 'Pword313';
        break;

    case "maths300":
        $form="form";
        $action="http://www.maths300.esa.edu.au/index.php/component/user/";
        $method="POST";
        $formArray['username'] = '330385';
        $formArray['passwd'] = 'fintona2007';
        break;

default:
	echo "Did not recognise the site";
break;

}






?>

<html>
<head>
<title>Redirecting to <? echo $to ?></title>

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
