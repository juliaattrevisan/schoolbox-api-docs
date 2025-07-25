<?php

use GuzzleHttp\Exception as GuzzleException;
use GuzzleHttp\Exception\RequestException as RequestException;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\IConfigLoader;

/* Script to automatically login to synergetic portal based on existing login creditials */
require_once __DIR__ . '/../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();

/** @var IConfigLoader $config */
$config = $kernel->get('config');

$user = $_SESSION['user'];
$url = new \Schoolbox\Url\Uri($config->get('external_portal'));

/* If the user has already passed by the login, we can go directly to the page */
if ($_SESSION['communityLogin'] ?? false) {
    if (isset($_GET['page'])) {
        $url = $url->withPath('/' . $_GET['page'] . '.aspx');
    } else {
        // This seems to be the default post-login landing page
        $url = $url->withPath('/stures.aspx');
    }

    $query = $url->getQueryAsArray();
    if (isset($_GET['id'])) {
        $query['id'] = $_GET['id'];
    }
    if (isset($_GET['sn'])) {
        $query['sn'] = $_GET['sn'];
    }

    /* If no menu requested turn it off */
    if (isset($_GET['menu'])) {
        $query['menu'] = 0;
    }
    $url = $url->appendQueryParameters($query);

    header('Location: ' . $url);
    exit;
} else {
    /* Setup form variable */
    $url = $url->withPath('/login.aspx');
    $form   = "Form1";
    $action = (string)$url;

    /* First contact the site to get some CSRF hacks */
    $method = "POST";

    /** @var \GuzzleHttp\Client $httpClient */
    $httpClient = $kernel->get('httpClient');

    try {
        $headers = ['User-Agent' => 'schoolbox/' . $config->get('site_version')];
        /**
         * check scheme, if webcal, update the scheme to http
         * libcurl which is what `GuzzleClient->get` is using under the hood doesnt understand webmail
         * so lets just replace that here to http. and hope the remote server will redirect to https
         * not just error out, or serve the data on http.
         */
        $login = $httpClient->get($url, ['headers' => $headers])->getBody()->getContents();
    } catch (RequestException | GuzzleException\ConnectException | GuzzleException\GuzzleException $e) {
        throw new SchoolboxException(
            message: "Community Portal Login unavailable. Please try again later",
            code: 0,
            previous: $e,
            httpStatusCode: null,
            /* FIXME This was supposed to put extra logging to the error screen, but for some reason...won't
             *  nvm...it's because the iframe accesses synRedir.php instead of just synRedir, which is a phplib
             *  thing where it loads outside of the SitePage context.
             */
            information: $e->getMessage()
        );
    }

    /* Set the URL to redirect the user to after login */
    if (isset($_GET['page'])) {
        if (isset($_GET['menu'])) {
            $action = $action . '?ReturnUrl=' . urlencode($_GET['page'] . '.aspx?menu=0');
        } else {
            $action = $action . '?ReturnUrl=' . urlencode($_GET['page']) . '.aspx';
        }
    }

    preg_match('/id="__VIEWSTATE" value="(.*?)"/', $login, $viewState);
    preg_match('/id="__EVENTVALIDATION" value="(.*?)"/', $login, $eventValidation);
    preg_match('/id="__VIEWSTATEGENERATOR" value="(.*?)"/', $login, $viewStateGenerator);
    preg_match('/id="__VIEWSTATEENCRYPTED" value="(.*?)"/', $login, $viewStateEncrypted);
    preg_match('/name="__RequestVerificationToken" type="hidden" value="(.*?)"/', $login, $requestVerificationToken); // the AntiForgeryToken check was added in CommPortal version 71.62
    preg_match('/name="(.*?)" value="Login"/', $login, $loginButton);
    preg_match('/name="(.*?)" type="text"/', $login, $loginUsername);
    preg_match('/name="(.*?)" type="password"/', $login, $loginPassword);

    // validate required fields (whilst ignoring CSRF fields):
    if (empty($loginUsername) || empty($loginPassword) || empty($loginButton)) {
        /* If we can't infer a username/password/login button, then we're looking some weird screen
         * that we can't interpret, such as the microsoft login form that asks for your email address.
         * We can't autosubmit that.
         *
         * If we can do it on users' behalf, we will: except where we can't.
         * In such cases, just present the screen that was given to us, and rely on users to fulfil it.
         */
        $page->localRedir($url);
    }

    // CommPortal (71.62) changed the login form, which breaks this CSRF hack. check for this
    if (!empty($requestVerificationToken[1])) {
    /* if request verify token found, redirect to the login page, we can't handle this
     * as it requires a cookie to be set on the $url which then matches to the form entry
     * and browsers won't allow us to set a cookie on a different domain.
     */
        $page->localRedir($url);
    }


    $password = $_SESSION['password'];
    $formArray['__VIEWSTATE'] = $viewState[1] ?? '';
    $formArray['__EVENTVALIDATION'] = $eventValidation[1] ?? '';
    $formArray['__EVENTARGUMENT'] = '';
    $formArray['__EVENTTARGET'] = '';
    $formArray['__LASTFOCUS'] = '';
    $formArray['__VIEWSTATEGENERATOR'] = $viewStateGenerator[1] ?? '';
    $formArray['__VIEWSTATEENCRYPTED'] = $viewStateEncrypted[1] ?? '';
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
<title>Redirecting to <?php echo $url ?></title>

</head>

<?php
echo "<body>";

echo"<form method='{$method}' name='{$form}' action='{$action}'>";
$nojs = 0;
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
