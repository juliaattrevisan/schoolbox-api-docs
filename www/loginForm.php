<?php

require_once __DIR__ . '/../include/Bootstrap.php';
session_start();

if ((isset($_POST['username'])) && (isset($_POST['password']) && $_POST['password'] && trim($_POST['username']))) {
    $sessionHandler = new Session;

    // Authentication
    $authenticated = false;
    $message       = null;

    if(isset($_POST['rememberme']) && $_POST['rememberme'] == 1){
        $remember = true;
    }
    else {
        $remember = false;
    }

    //first check for brute force attempt
    if ($sessionHandler->checkBruteForce()) {
        $message =
            'You have attempted more than ' .
            Config::get('login_allowed') . ' logins in ' .
            Config::get('login_grace') . ' seconds.<br>'.
            'Please try again in a short while.'
        ;
    }
    else {
        try {
            $authenticated =
                $sessionHandler->authenticate(trim($_POST['username']), $_POST['password'])
                && $sessionHandler->create(trim($_POST['username']), $remember);
        }
        catch (Exception_Schoolbox_NotFound $e) {
            $message = 'The username or password you typed is incorrect, please try again.';
        }
        catch (Exception_Schoolbox_AccountDisabled $e) {
            $message = 'This account is currently disabled.';
        }
        catch (Exception_Schoolbox_AccessDenied $e) {
            $message = 'The username or password you typed is incorrect, please try again.';
        }
        if ($authenticated) {
            if (isset($_POST['page'])
                && $_POST['page']
                && $_POST['page'] != '/'
                && $_POST['page'] != '/login'
                && $_POST['page'] != '/login/'
            ) {
                $page = rawurldecode($_POST['page']);
                SitePage::localRedir($page);
            }
            else {
                SitePage::localRedir('/');
            }
        }
        else {
            //get rid of session started earlier to stop it conflicting with next login
            $sessionHandler->destroy();
            if (empty($message)) {
                $message = 'The username or password you typed is incorrect, please try again.';
            }
        }
    }

    if (isset($message)) {
        $page = new SitePage(false);
        $page->goLoginPage($message, $_POST['page']);
    }
}
else {
    SitePage::localRedir('/login/');
}
