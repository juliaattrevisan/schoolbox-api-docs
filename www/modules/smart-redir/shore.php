<?php

//A Semi-Smart Autologon Script
//It remains to be seen who falls for it
//010405, time to pull an April Fools!!!
//lwcawood


if ($_GET) {
    $to = $_GET['directTo'];
    switch ($to) {
        case "infotrac-history":
            $form = "form";
            $action = "http://infotrac.galegroup.com/itweb/shoreschool";
            $method = "GET";
            $formArray['db'] = 'History';
            $formArray['id'] = 'excellent';
            break;

        case "infotrac-literacy":
            $form = "form";
            $action = "http://infotrac.galegroup.com/itweb/shoreschool";
            $method = "GET";
            $formArray['db'] = 'LitRC';
            $formArray['id'] = 'excellent';
            break;

        case "infotrac-science":
            $form = "form";
            $action = "http://infotrac.galegroup.com/itweb/shoreschool";
            $method = "GET";
            $formArray['db'] = 'SciRC';
            $formArray['id'] = 'excellent';
            break;

        case "ebsco-history":
            $form = "Form1";
            $action = "http://search.ebscohost.com/login.aspx?profile=ehost&defaultdb=khh";
            $method = "POST";
            $formArray['__VIEWSTATE'] = "/wEPDwUJMjEzNzg1OTE1ZGQ=";
            $formArray['FormSubmit'] = 'true';
            $formArray['user'] = 'shore';
            $formArray['password'] = "shore";
            $formArray['btnLogin'] = "Login";
            $formArray['authtype'] = "uid";
            $formArray['ref'] = "";
            break;

        case "ebsco-bib":
            $form = "Form1";
            $action = "http://search.ebscohost.com/login.aspx?profile=brc";
            $method = "POST";
            $formArray['__VIEWSTATE'] = "/wEPDwUJMjEzNzg1OTE1ZGQ=";
            $formArray['FormSubmit'] = 'true';
            $formArray['user'] = 'shore';
            $formArray['password'] = "shore";
            $formArray['btnLogin'] = "Login";
            $formArray['authtype'] = "uid";
            $formArray['ref'] = "";
            break;

        case "ebsco-pov":
            $form = "Form1";
            $action = "http://search.ebscohost.com/login.aspx?site=pov";
            $method = "POST";
            $formArray['__VIEWSTATE'] = "/wEPDwUJMjEzNzg1OTE1ZGQ=";
            $formArray['FormSubmit'] = 'true';
            $formArray['user'] = 'shore';
            $formArray['password'] = "shore";
            $formArray['btnLogin'] = "Login";
            $formArray['authtype'] = "uid";
            $formArray['ref'] = "";
            break;

        case "ebsco-anh":
            $form = "Form1";
            $action = "http://search.ebscohost.com/login.aspx?profile=ehost&defaultdb=anh";
            $method = "POST";
            $formArray['__VIEWSTATE'] = "/wEPDwUJMjEzNzg1OTE1ZGQ=";
            $formArray['FormSubmit'] = 'true';
            $formArray['user'] = 'shore';
            $formArray['password'] = "shore";
            $formArray['btnLogin'] = "Login";
            $formArray['authtype'] = "uid";
            $formArray['ref'] = "";
            break;

        case "ebsco-sch":
            $form = "Form1";
            $action = "http://search.ebscohost.com/login.aspx?profile=ehost&defaultdb=sch";
            $method = "POST";
            $formArray['__VIEWSTATE'] = "/wEPDwUJMjEzNzg1OTE1ZGQ=";
            $formArray['FormSubmit'] = 'true';
            $formArray['user'] = 'shore';
            $formArray['password'] = "shore";
            $formArray['btnLogin'] = "Login";
            $formArray['authtype'] = "uid";
            $formArray['ref'] = "";
            break;


        case "ebsco-lrc":
            $form = "Form1";
            $action = "http://search.ebscohost.com/login.aspx?profile=lrc";
            $method = "POST";
            $formArray['__VIEWSTATE'] = "/wEPDwUJMjEzNzg1OTE1ZGQ=";
            $formArray['FormSubmit'] = 'true';
            $formArray['user'] = 'shore';
            $formArray['password'] = "shore";
            $formArray['btnLogin'] = "Login";
            $formArray['authtype'] = "uid";
            $formArray['ref'] = "";
            break;


        case "jstor":
            $form = "frmLogin";
            $action = "https://www.jstor.org/action/doLogin";
            $method = "POST";
            $formArray['redirectUri'] = "/action/showBasicSearch";
            $formArray['loginUri'] = "/action/showLogin?redirectUri=%2Faction%2FshowBasicSearch";
            $formArray['login'] = "shoreschool";
            $formArray['password'] = "2shore4jstor";
            break;


        case "infomit-sage":
            $form = "loginForm";
            $action = "http://search.informit.com.au/search;res%3DSAGE";
            $method = "POST";
            $formArray['username'] = "sydch";
            $formArray['password'] = "423441";
            break;

        case "infomit-ausport":
            $form = "loginForm";
            $action = "http://search.informit.com.au/search;res%3DAUSPORT";
            $method = "POST";
            $formArray['username'] = "sydch";
            $formArray['password'] = "423441";
            break;

        case "infomit-tvnews":
            $form = "loginForm";
            $action = "http://search.informit.com.au/search;res%3DTVNEWS";
            $method = "POST";
            $formArray['username'] = "sydch";
            $formArray['password'] = "423441";
            break;

        case "infomit-arch":
            $form = "loginForm";
            $action = "http://search.informit.com.au/search;res%3DARCH";
            $method = "POST";
            $formArray['username'] = "sydch";
            $formArray['password'] = "423441";
            break;

        case "infomit-apais":
            $form = "loginForm";
            $action = "http://search.informit.com.au/search;res%3DAPAIS";
            $method = "POST";
            $formArray['username'] = "sydch";
            $formArray['password'] = "423441";
            break;

        case "infomit-aei":
            $form = "loginForm";
            $action = "http://search.informit.com.au/search;res%3DAEI";
            $method = "POST";
            $formArray['username'] = "sydch";
            $formArray['password'] = "423441";
            break;


        case "worldbook":
            $form = "loginForm";
            $action = "https://www.worldbookonline.com/wb/Home";
            $method = "POST";
            $formArray['subacct'] = "AU01024";
            $formArray['uid'] = "shore1";
            $formArray['pwd'] = "shore";
            $formArray['auth'] = "UidPassword";

            break;

        case "brainpop":
            $form = "form";
            $action = "http://www.brainpop.com/user/loginDo.weml";
            $method = "GET";
            $formArray['user'] = 'shorelibrary';
            $formArray['password'] = 'shore';
            break;


        case "librarywebs";
            $form = "Login";
            $action = "http://online.librarywebs.net/Login.aspx";
            $method = "post";
            $formArray = array('inputUsername' => 'shore',
               'inputPassword' => 'school',
               '__VIEWSTATE' => 'dDwxOTM2NjQ0MDE7dDw7bDxpPDE+Oz47bDx0PDtsPGk8MT47aTwzPjs+O2w8dDw7bDxpPDc+Oz47bDx0PHA8cDxsPFZpc2libGU7PjtsPG88dD47Pj47Pjs7Pjs+Pjt0PHA8cDxsPFZpc2libGU7PjtsPG88Zj47Pj47Pjs7Pjs+Pjs+PjtsPGJ0bkxvZ2luOz4+xKZzwlFnHiKtVcQeIqLtTbbykaA=',
              'btnLogin'    => '',
            );

    break;


        case "readplus":
            $form = "form";
            $action = "http://www.readplus.com.au/loginck.php";
            $method = "GET";
            $formArray['userid'] = 'shore';
            $formArray['password'] = '607';
            break;

        case "sirs":
            $form = "loginForm";
            $action = "http://ars.sirs.com/custlogin";
            $method = "POST";
            $formArray['CustNum'] = "shore2060";
            $formArray['password'] = "learning";

            break;

        case "weblinks":
            $form = "form";
            $action = "http://www.weblinksresearch.com.au/login/online/online.cfm";
            $method = "POST";
            $formArray['entereduser'] = 'shore';
            $formArray['EnteredPassword'] = '607';
            break;

        case "elibrary":
            $form = "loginForm";
            $action = "http://elibrary.bigchalk.com/elibweb/australia/do/login?ts=22F92D51946C45F37BB41FE64E1CCE1A_1264152496474";
            $method = "POST";
            $formArray['userid'] = "shore2060";
            $formArray['password'] = "learning";

            break;

        case "britannica":
            $form = "loginForm";
            $action = "https://school.eb.com.au/login";
            $method = "POST";
            $formArray['username'] = "shoreschool";
            $formArray['password'] = "northsydney";
            $formArray['target'] = '/';
            break;

        case "onguard":
            $form = "formLogin";
            $action = "http://www.1300acepro.com/OnGuardACEPro/content/doLogin.asp";
            $method = "POST";
            $formArray['login_username'] = 'dtw';
            $formArray['login_password'] = 'dave';
            $formArray['schoolid']      = 'shore.nsw';
            break;

        case "studyskills":
            $form = "form";
            $action = "http://www.studyskillshandbook.com.au/admin/users/login.php?ce=0&group=1&url=www.studyskillshandbook.com.au/inside/index.html";
            $method = "POST";
            $formArray['LoginType'] = 'old';
            $formArray['username']  = 'forshoreonly';
            $formArray['password']  = '35results';
            break;

        case "dramaonline":
            $form = "form";
            $action = "https://www.dramaonlinelibrary.com/login?recentUrl=/custom-browse?docid=nationalTheatreCollection";
            $method = "POST";
            $formArray['login-username']    = 'SHORE';
            $formArray['login-password']    = 'Dr@maOnline';
            break;


        default:
            echo "Did not recognise the site";
            break;
    }






    ?>

<html>
<head>
<title>Redirecting to <?php echo htmlentities($to) ?></title>

</head>

    <?php
    echo "<body>";

    echo"<form method='{$method}' name='{$form}' action='{$action}'>";
    if (is_array($formArray)) {
        foreach ($formArray as $key => $value) {
            if ($key == "submit") {
                echo "<br><br><br><br><br><br><br><br><div align='center'>Please $value to continue<br>";
                echo "<input name='$key' type='submit' value='$value'></div>";
                $nojs = 1;
            } else {
                echo "<input name='$key' type='hidden' value='$value'>";
            }
        }
    }
    ?>
</form>
<script>
    <?php
    if (!($nojs)) {
        echo "document.{$form}.submit();";
    }
    ?>
</script>
</body>
</html>
    <?php
}
?>
