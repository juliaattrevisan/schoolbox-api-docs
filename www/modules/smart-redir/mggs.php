<?php
require_once __DIR__ . '/../../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();

//A Semi-Smart Autologon Script
//It remains to be seen who falls for it
//010405, time to pull an April Fools!!!
//lwcawood

if ($_GET) {
    $to = $_GET['directTo'];
    switch ($to) {
        case "worldbook":
            $form                 = "loginForm";
            $action               = "https://www.worldbookonline.com/wb/products?ed=all";
            $method               = "POST";
            $formArray['subacct'] = 'AU04114';
            $formArray['uid']     = "melbggs";
            $formArray['pwd']     = "melbourne";
            $formArray['auth']    = "UidPassword";
            break;

        case "worldbook-kids":
            $form                 = "loginForm";
            $action               = "https://www.worldbookonline.com/wb/products?ed=all";
            $method               = "POST";
            $formArray['subacct'] = 'AU04114';
            $formArray['uid']     = "melbggs";
            $formArray['pwd']     = "melbourne";
            $formArray['auth']    = "UidPassword";
            $formArray['tu']      = 'https://worldbookonline.com/kids/home';
            break;

        case "worldbook-early":
            $form                 = "loginForm";
            $action               = "https://www.worldbookonline.com/wb/products?ed=all";
            $method               = "POST";
            $formArray['subacct'] = 'AU04114';
            $formArray['uid']     = "mggmoh";
            $formArray['pwd']     = "mggmoh";
            $formArray['auth']    = "UidPassword";
            $formArray['tu']      = 'https://www.worldbookonline.com/ewol/home';
            break;

        case "worldbook-ebooks":
            $form                 = "loginForm";
            $action               = "https://www.worldbookonline.com/wb/products?ed=all";
            $method               = "POST";
            $formArray['subacct'] = 'AU04114';
            $formArray['uid']     = "mggmoh";
            $formArray['pwd']     = "mggmoh";
            $formArray['auth']    = "UidPassword";
            $formArray['tu']      = 'https://www.worldbookonline.com/wb/ebooks';
            break;

        case "britannica":
            $form                  = "loginForm";
            $action                = "https://school.eb.com.au/login";
            $method                = "POST";
            $formArray['username'] = "mggs";
            $formArray['password'] = "mggs";
            $formArray['target']   = '/';
            break;

        case "britannica-primary":
            $form                  = "loginForm";
            $action                = "https://school.eb.com.au/levels/primary";
            $method                = "POST";
            $formArray['username'] = "mggs";
            $formArray['password'] = "mggs";
            $formArray['remember'] = 'false';
            $formArray['target']   = 'https://school.eb.com.au/levels/primary';
            break;

        case "britannica-kids":
            $form                  = "loginForm";
            $action                = "https://secure.britannica.com/login";
            $method                = "POST";
            $formArray['username'] = "mggs";
            $formArray['password'] = "mggs";
            $formArray['remember'] = 'false';
            $formArray['fail']     = 'http://kids.britannica.com/failedlogin';
            $formArray['target']   = 'http://kids.britannica.com/';
            break;

        case "oxford":
            $form                     = "form";
            $action                   = "http://www.oxfordreference.com/AUTHENTICATE";
            $method                   = "POST";
            $formArray['scope']       = 'global';
            $formArray['username']    = '';
            $formArray['password']    = '';
            $formArray['librarycard'] = 'mggs12345';
            break;

        case "oxford_classic":
            $form              = "form";
            $action            = "http://oxfordre.com/LOGIN";
            $method            = "POST";
            $formArray['dest'] = '/classics';
            $formArray['user'] = 'melbgirls';
            $formArray['pass'] = 'mggs1893';
            break;

        case "oxford_greek":
            $form              = "form";
            $action            = "https://www.oxfordreference.com/LOGIN";
            $method            = "POST";
            $formArray['dest'] = '/view/10.1093/acref/9780195170726.001.0001/acref-9780195170726';
            $formArray['user'] = 'melbgirls';
            $formArray['pass'] = 'mggs1893';
            break;

        case "oxford_shakespeare":
            $form              = "form";
            $action            = "https://www.oxfordreference.com/LOGIN";
            $method            = "POST";
            $formArray['dest'] = '/view/10.1093/acref/9780198117353.001.0001/acref-9780198117353';
            $formArray['user'] = 'melbgirls';
            $formArray['pass'] = 'mggs1893';
            break;

        case "oxford_dictionary":
            $form              = "form";
            $action            = "https://www.oed.com/LOGIN";
            $method            = "POST";
            $formArray['dest'] = '/';
            $formArray['user'] = 'melbgirls';
            $formArray['pass'] = 'mggs1893';
            break;

        case "librarywebs";
            $form      = "Login";
            $action    = "http://online.librarywebs.net/Login.aspx";
            $method    = "post";
            $formArray = [
                'inputUsername' => 'mggs',
                'inputPassword' => 'mggs05',
                '__VIEWSTATE'   => '/wEPDwUKMTQ4NzAxMjMzMA9kFgICAQ9kFgQCAQ9kFgICBw8PFgIeB1Zpc2libGVnZGQCAw8PFgIfAGhkZBgBBR5fX0NvbnRyb2xzUmVxdWlyZVBvc3RCYWNrS2V5X18WAQUIYnRuTG9naW4VXANH39VlWJdsuQPLrmub5mrDNg==',
                'btnLogin'      => '',
            ];

            break;

        case "active-history":
            $form                       = "form";
            $action                     = "http://www.activehistory.co.uk/amember/plugins/protect/new_rewrite/login.php?v=-1,2,6,7,8,9,10,11,12,13,14&url=/main_area/index.php";
            $method                     = "POST";
            $formArray['amember_login'] = 'melbgirls';
            $formArray['amember_pass']  = 'mggs1893';
            //$formArray['login_attempt_id'] = '1312519491';
            break;

        case "magpies":
            $form                     = "form";
            $action                   = "https://www.magpies.net.au/wp-login.php";
            $method                   = "POST";
            $formArray['log']         = 'mggs';
            $formArray['pwd']         = 'mggs123';
            $formArray['redirect_to'] = "https://www.magpies.net.au/the-source/";
            break;

        case "ebsco":
            $form                     = "Form1";
            $action                   = "http://search.ebscohost.com/login.aspx";
            $method                   = "POST";
            $formArray['__VIEWSTATE'] = "/wEPDwUJMjEzNzg1OTE1ZGQ=";
            $formArray['FormSubmit']  = 'true';
            $formArray['user']        = 'mggs';
            $formArray['password']    = "grammar11";
            $formArray['btnLogin']    = "Login";
            $formArray['authtype']    = "uid";
            $formArray['ref']         = "";
            break;

        case "infotrac":
            $form            = "form";
            $action          = "https://galeapps.gale.com/apps/auth";
            $method          = "POST";
            $formArray['password'] = 'literature';
            $formArray['prodId'] = 'GLS';
            $formArray['productLongName'] = 'Gale Literature';
            $formArray['userGroupName'] = 'melgg';
            $formArray['origURL'] = 'https://go.gale.com/ps/start.do?p=GLS&u=melgg';
            $formArray['isReattempt'] = 'true';

            break;

        case "storybox":
            $form                  = "SchoolLoginForm_schoolsLoginForm";
            $action                = "https://storyboxlibrary.com.au/login_remote";
            $method                = "POST";
            $formArray['username'] = 'Morris Hall';
            $formArray['password'] = 'SBLmorris20';
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

    echo "<form method='{$method}' name='{$form}' action='{$action}'>";
    if (is_array($formArray)) {
        foreach ($formArray as $key => $value) {
            if ($key == "submit") {
                echo "<br><br><br><br><br><br><br><br><div align='center'>Please $value to continue<br>";
                echo "<input name='$key' type='submit' value='$value'></div>";
                $nojs = 1;
            } else {
                $nojs = 0;
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
