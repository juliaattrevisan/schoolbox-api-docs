<?php
/**
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Application\Login;

use Alaress\Web\Controller\Base;
use Schoolbox\Application\Exception;
use Schoolbox\User;
use Session;
use Symfony\Component\HttpFoundation\IpUtils;
use Zend\Uri\Uri;

class Controller extends Base
{
    public const LOGIN_ERROR_FLASH = 'login';

    /**
     * Displays login page, will show error if XHR, will redirect to SAML if enabled
     * @api   GET /login
     * @since 18.2
     *
     * @param string|null $error
     * @param string|null $page
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getLogin($error = null, $page = null): void
    {

        // If you try to access the login screen but you are already logged in, just go home
        if ($this->getSession()->getAuthUser() instanceof User && $this->getSession()->getAuthUser()->getId()) {
            $this->getResponse()->redirect->to('/');
        }

        $samlAuth = $this->getRequest()->query->get('samlAuth');
        $page = $page ?: $this->getRequest()->query->get('page');
        $pageUri = new Uri($page);

        // Don't output login screen if XHR request
        if ($this->isJsonRequest() || $this->isXhrRequest()) {
            $this->getResponse()->content->set($this->buildJsonOutput(['error' => 'Unauthenticated']));
            $this->getResponse()->headers->set('Unauthenticated', 401);
            exit;
        }

        // is SAML enabled?
        // don't show the login page, redirect to the SAML login page.
        // (unless ?samlLogin=0 is set)
        // (or the users IP exists in the blacklist
        if (( $this->getConfigLoader()->get('saml_enabled') || $this->samlForce() )
            && ($this->getConfigLoader()->get('saml_sso_url') !== '' || $this->getConfigLoader()->get('saml_idp_metadata_url') !== '')
            && $samlAuth !== '0'
            && (!$this->samlBlacklist() || $samlAuth === '1')) {
            $redirectUrl = '/saml/index.php';
            if ($pageUri->isValidRelative()) {
                $redirectUrl .= '?page=' . rawurlencode($pageUri);
            }
            $this->getResponse()->redirect->to($redirectUrl, 303);
            return;
        }

        $getParams = ['samlAuth' => $samlAuth, 'page' => $page,];
        $data['requestParams'] = http_build_query($getParams);

        //Determine if page redirect is valid
        if ($pageUri->isValidRelative() && $page !== '/login' && $page !== '/login/' ) {
            $data['page'] = $pageUri->toString();
        } else {
            $data['page'] = '';
        }

        // Determine the login background image to use
        $backgrounds = explode(',', $this->getConfigLoader()->get('skin_login_bg'));

        if (count($backgrounds) && $backgrounds[0]) {
            $data['login_bg'] = '/storage/image.php?hash=' . $backgrounds[array_rand($backgrounds)] . '&size=maxsize';
        } else {
            $data['login_bg'] = '/images/core/login-bg.jpg';
        }

        // Display an error if appropriate to the user
        if ($error) {
            $data['error'] = $error;
        } else {
            if ($this->session->getSegment(self::class)->getFlash(self::LOGIN_ERROR_FLASH)) {
                $data['error'] = 'Your session has expired. Please log in below to continue browsing the portal.';
            } else {
                $data ['error'] = null;
            }
        }

        $this->getResponse()->content->set($this->buildUnthemedHtmlOutput('@login/login.twig', $data));
    }


    /**
     * Schoolbox login form post, where your username and password is validated before letting you in
     * If successful will redirect to / or $page.
     * If unscuccessful will display login page with error
     *
     * @api   POST /login
     * @since 18.2
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function postLogin(): void
    {
        $username = trim($this->getRequest()->post['username']);
        $password = $this->getRequest()->post['password'];
        $page = $this->getRequest()->post['page'];
        $pageUri = new Uri($page);
        $tandc = $this->getRequest()->post['tandc'] === '1';
        $remember = $this->getRequest()->post['rememberme'] === '1';

        if (strlen($username) && strlen($password)) {
            $sessionHandler = new Session;

            if ($sessionHandler->checkBruteForce()) {
                // first check for brute force attempt
                $message =
                    'You have attempted more than ' .
                    $this->getConfigLoader()->get('login_allowed') . ' logins in ' .
                    $this->getConfigLoader()->get('login_grace') . ' seconds. ' .
                    'Please try again in a short while.';
            } elseif ($tandc !== true && strlen($this->getConfigLoader()->get('login_terms_and_conditions'))) {
                // then check if they checked the terms and conditions checkbox
                $message = $this->getConfigLoader()->get('login_terms_and_conditions_error');
            } else {
                try {
                    $user = $sessionHandler->authenticate($username, $password);
                    if ($user instanceof User) {
                        $authenticated = $sessionHandler->create($user->getUsername(), $remember);
                    } else {
                        $message = 'The username or password you typed is incorrect, please try again.';
                    }
                } catch (Exception\NotFound $e) {
                    $message = 'The username or password you typed is incorrect, please try again.';
                } catch (Exception\AccountDisabled $e) {
                    $message = 'This account is currently disabled.';
                } catch (Exception\AccessDenied $e) {
                    $message = 'The username or password you typed is incorrect, please try again.';
                } catch (Exception\PasswordExpired $e) {
                    $message = $e->getMessage();
                }
                if ($authenticated) {
                    if (strlen($page)
                        && $pageUri->isValidRelative()
                        && $page !== '/'
                        && $page !== '/login'
                        && $page !== '/login/'
                    ) {
                        $page = rawurldecode($page);
                        $this->getResponse()->redirect->to($page, 303);
                    } else {
                        $this->getResponse()->redirect->to('/', 303);
                    }
                } else {
                    //get rid of session started earlier to stop it conflicting with next login
                    $sessionHandler->destroy();
                    if (empty($message)) {
                        $message = 'The username or password you typed is incorrect, please try again.';
                    }
                }
            }

            if (strlen($message)) {
                $this->getLogin($message, $page);
            }
        } else {
            $this->getLogin('Please provide a username and password', $page);
        }
    }

    /**
     * Check if the users IP exists in the SAML blacklist.
     * @return bool
     */
    private function samlBlacklist(): bool
    {
        $ipBlacklist = array_map('trim', explode(',', $this->getConfigLoader()->get('saml_ip_blacklist')));
        return IpUtils::checkIp(Session::getIP(), $ipBlacklist);
    }

    /**
     * Check if the users IP exists in the SAML whitelist.
     * @return bool
     */
    private function samlForce(): bool
    {
        $ipWhitelist = array_map('trim', explode(',', $this->getConfigLoader()->get('saml_ip_whitelist')));
        return IpUtils::checkIp(Session::getIP(), $ipWhitelist);
    }
}
