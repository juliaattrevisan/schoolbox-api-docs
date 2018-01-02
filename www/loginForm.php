<?php

require_once __DIR__ . '/../library/Bootstrap.php';
session_start();

use Schoolbox\Application\Exception\NotFound as SchoolboxNotFoundException;
use Schoolbox\Application\Exception\AccessDenied as SchoolboxAccessDeniedException;
use Schoolbox\Application\Exception\AccountDisabled as SchoolboxAccountDisabledException;

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

    if ($sessionHandler->checkBruteForce()) {
        // first check for brute force attempt
        $message =
            'You have attempted more than ' .
            Config::get('login_allowed') . ' logins in ' .
            Config::get('login_grace') . ' seconds.<br>'.
            'Please try again in a short while.'
        ;
    } elseif (strlen(Config::get('login_terms_and_conditions')) && empty($_POST['tandc'])) {
        // then check if they checked the terms and conditions checkbox
        $message = Config::get('login_terms_and_conditions_error');
    } else {
        try {
            $username = trim($_POST['username']);
            $user = $sessionHandler->authenticate($username, $_POST['password']);
            if ($user instanceof \Schoolbox\User) {
                $authenticated = $sessionHandler->create($user->getUsername(), $remember);
            }
            else {
                $message = 'The username or password you typed is incorrect, please try again.';
            }
        }
        catch (SchoolboxNotFoundException $e) {
            $message = 'The username or password you typed is incorrect, please try again.';
        }
        catch (SchoolboxAccountDisabledException $e) {
            $message = 'This account is currently disabled.';
        }
        catch (SchoolboxAccessDeniedException $e) {
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
