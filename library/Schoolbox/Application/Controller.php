<?php
/**
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Application;

use Alaress\Web\Controller\Base;
use ReCaptcha;
use Schoolbox\User;
use Session;

class Controller extends Base
{
    public const LOGIN_ERROR_FLASH = 'login';

    /**
     * @var \Schoolbox\Error\Mapper
     */
    private $errorMapper;

    /** @var ReCaptcha $reCaptcha */
    private $reCaptcha;

    /**
     * Controller constructor.
     *
     * @param \Schoolbox\Error\Mapper $errorMapper
     * @param ReCaptcha               $reCaptcha
     */
    public function __construct(\Schoolbox\Error\Mapper $errorMapper, ReCaptcha $reCaptcha)
    {
        $this->errorMapper = $errorMapper;
        $this->reCaptcha = $reCaptcha;
    }

    /**
     * Provides a form that users can use to submit support requests or feedback via.  Can be accessed without session
     * @api   GET /feedback
     * @level 18.2
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getFeedback(): void
    {
        $data = [];
        $authUser = $this->getSession()->getAuthUser();
        if ($authUser instanceof User && !$authUser->getId()) {
            $data = ['recaptcha' => $this->reCaptcha->form()];
        }

        $data['types'] = explode(',', $this->getConfigLoader()->get('site_feedback_types'));

        $this->getResponse()->content->set($this->buildHtmlOutput('@application/feedback.twig', $data)
                                                ->setTitle($this->getConfigLoader()->get('feedback_text')));
    }

    /**
     * Thank you page after submitting feedback, can be accessed with and without a session
     * @api   GET /feedback/thank-you
     * @level 18.2
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getFeedbackReceipt()
    {
        $this->getResponse()->content->set($this->buildHtmlOutput('@application/feedback-thankyou.twig', [])
                                                ->setTitle('Message Delivered'));
    }


    /**
     * Schoolbox policy page, can be accessed with and without a session
     * @api   GET /policy
     * @level 18.2
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getPolicy()
    {
        $this->getResponse()->content->set($this->buildHtmlOutput('@application/policy.twig', []));
    }

    /**
     * Displays login page, will show error if XHR, will redirect to SAML if enabled
     * @api   GET /login
     * @level 18.2
     *
     * @param string|null $error
     * @param string|null $page
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getLogin($error = null, $page = null): void
    {

        $samlAuth = $this->getRequest()->query->get('samlAuth');
        $page = $page ?: $this->getRequest()->query->get('page');

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
        if ($this->getConfigLoader()->get('saml_enabled')
            && ($this->getConfigLoader()->get('saml_sso_url') !== '' || $this->getConfigLoader()->get('saml_idp_metadata_url') !== '')
            && $samlAuth !== '0'
            && (!$this->samlBlacklist() || $samlAuth === '1')) {
            $redirectUrl = '/saml/index.php';
            if (isset($_GET['page'])) {
                $redirectUrl .= '?page=' . urlencode($page);
            }
            $this->getResponse()->redirect->to($redirectUrl, 303);
        }

        $getParams = ['page' => $page, 'samlAuth' => $samlAuth];
        $data['requestParams'] = http_build_query($getParams);

        //Determine if page redirect is valid
        if ($page && preg_match('#^[\w_=\-:\/.\&\?]+$#i', $page)) {
            $data['page'] = $page;
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

        $this->getResponse()->content->set($this->buildUnthemedHtmlOutput('@application/login.twig', $data));
    }


    /**
     * Schoolbox login form post, where your username and password is validated before letting you in
     * If successful will redirect to / or $page.
     * If unscuccessful will display login page with error
     *
     * @api   POST /login
     * @level 18.2
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function postLogin(): void
    {
        $username = trim($this->getRequest()->post['username']);
        $password = $this->getRequest()->post['password'];
        $page = $this->getRequest()->post['page'];
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
                    if ($user instanceof \Schoolbox\User) {
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
        $ipBlacklist = $this->getConfigLoader()->get('saml_ip_blacklist');
        foreach (explode(',', $ipBlacklist) as $ip) {
            if ($ip === Session::getIP()) {
                return true;
            }
        }
        return false;
    }
}
