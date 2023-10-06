<?php

/**
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Application\Login;

use Alaress\Web\Controller\Base;
use Schoolbox\Application\Exception;
use Schoolbox\Auth\Remember\Remember;
use Schoolbox\Cdn\PrefixGenerator;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\User;
use Session;
use Symfony\Component\HttpFoundation\IpUtils;
use Schoolbox\Url\Uri;

class Controller extends Base
{
    public const LOGIN_ERROR_FLASH = 'login';

    /**
     * Prefix generator, used for generating a link to the fallback background.
     */
    private PrefixGenerator $prefixGenerator;

    /**
     * Login rememberer, used to remember a user's login.
     */
    private Remember $rememberer;

    /**
     * Constructor
     *
     * @param PrefixGenerator $prefixGenerator
     * @param Remember        $rememberer
     */
    public function __construct(PrefixGenerator $prefixGenerator, Remember $rememberer)
    {
        $this->prefixGenerator = $prefixGenerator;
        $this->rememberer      = $rememberer;
    }

    /**
     * Displays login page, will show error if XHR, will redirect to SAML if enabled
     * @api   GET /login
     * @since 18.2
     *
     * @param string|null $error
     * @param string|null $page
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     */
    public function getLogin($error = null, $page = null): void
    {
        $config = $this->getConfigLoader();

        // If you try to access the login screen but you are already logged in, just go home
        if ($this->getSession()->getAuthUser() instanceof User && $this->getSession()->getAuthUser()->getId()) {
            $this->response = $this->redirectSeeOther('/');
        }

        $samlAuth = $this->requestQuery->get('samlAuth');
        $page = $page ?: $this->requestQuery->get('page');
        $pageUri = $page ? new Uri($page) : null;

        // Don't output login screen if XHR request
        if ($this->isJsonRequest() || $this->isXhrRequest()) {
            $this->response = $this->responseAsJson(['error' => 'Unauthenticated'])
                                   ->withHeader('Unauthenticated', 401);
            return;
        }

        // is SAML enabled?
        // don't show the login page, redirect to the SAML login page.
        // (unless ?samlAuth=0 is set)
        // (or the users IP exists in the blacklist
        if (
            ($config->get('saml_enabled') || $this->samlForce())
            && ($config->get('saml_sso_url') !== '' || $config->get('saml_idp_metadata_url') !== '')
            && $samlAuth !== '0'
            && (!$this->samlBlacklist() || $samlAuth === '1')
        ) {
            $redirectUrl = '/saml/index.php';
            if ($pageUri && $pageUri->isAbsolutePath()) {
                $redirectUrl .= '?page=' . rawurlencode($pageUri);
            }
            $this->response = $this->redirectSeeOther($redirectUrl);
            return;
        }

        // Is OIDC enabled and switched on by default?
        // If so, redirect there.
        // (Reuse the ?samlAuth query string var for now for bypass?)
        if (
            ($config->get(ConfigMapper::NAME_OAUTH_ENABLE_SSO) || $this->ipMatches(ConfigMapper::NAME_OAUTH_SSO_IP_WHITELIST))
            && $config->get(ConfigMapper::_CLOUDWORK_OAUTH_IDENTITY_PROVIDER)
            && $samlAuth !== '0'
            && (!$this->ipMatches(ConfigMapper::NAME_OAUTH_SSO_IP_BLACKLIST) || $samlAuth === '1')
        ) {
            $redirectUrl = new Uri($this->routeGenerator->generate('loginOidc.getAuthorization'));
            if ($pageUri && $pageUri->isAbsolutePath()) {
                $redirectUrl = $redirectUrl->appendQueryParameters(['redirect' => $pageUri->__toString()]);
            }
            $this->response = $this->redirectSeeOther($redirectUrl->__toString());
            return;
        }

        $getParams = ['samlAuth' => $samlAuth, 'page' => $page,];
        $data['requestParams'] = http_build_query($getParams);

        //Determine if page redirect is valid
        if ($pageUri && $pageUri->isAbsolutePath() && $page !== '/login' && $page !== '/login/') {
            $data['page'] = (string)$pageUri;
        } else {
            $data['page'] = '';
        }

        // Build a set of login backgrounds: choose a random one in Twig
        $data['login_backgrounds'] = [];
        $backgrounds = explode(',', $config->get('skin_login_bg'));
        foreach ($backgrounds as $background) {
            if (!empty($background)) {
                $data['login_backgrounds'][] = '/storage/image.php?' . http_build_query(['hash' => $background, 'size' => 'maxsize']);
            }
        }
        if (empty($data['login_backgrounds'])) {
            $data['login_backgrounds'][] = $this->prefixGenerator->generate() . '/images/core/login-bg.jpg';
        }

        // Display an error if appropriate to the user
        if ($error) {
            $data['error'] = $error;
        } else {
            if ($this->session->getSegment(self::class)->getFlash(self::LOGIN_ERROR_FLASH)) {
                $data['error'] = 'Your session has expired. Please log in below to continue browsing the portal.';
            } else {
                $data['error'] = null;
            }
        }

        // Is this request from a known internal IP?
        // If so show the internal SSO link
        $data['requestFromInternalIp'] = false;
        if ($this->samlBlacklist()) {
            $data['requestFromInternalIp'] = true;
            $internalSsoUrl                = new Uri($this->getConfigLoader()->get(ConfigMapper::NAME_INSTANCE_REGISTRY_URL));
            $internalSsoUrlParams          = ['instance_id' => INSTANCE_ID];
            if ($data['page']) {
                $internalSsoUrlParams['redirect'] = $data['page'];
            }
            $data['internalSsoUrl'] = $internalSsoUrl
                ->appendQueryParameters($internalSsoUrlParams)
                ->withPath('/api/users/jwt');
        }

        $this->response = $this->responseFromTemplateUnthemed('@login/login.twig', $data);
    }


    /**
     * Schoolbox login form post, where your username and password is validated before letting you in
     * If successful will redirect to / or $page.
     * If unscuccessful will display login page with error
     *
     * @api   POST /login
     * @since 18.2
     *
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     */
    public function postLogin(): void
    {
        $username = trim($this->requestPost->get('username', ''));
        $password = $this->requestPost->get('password', '');
        $page = $this->requestPost->get('page');
        $tandc = $this->requestPost->get('tandc') === '1';
        $remember = $this->requestPost->get('rememberme') === '1';

        $message = '';
        if (strlen($username) && strlen($password)) {
            $sessionHandler = new Session();

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
                $authenticated = false;
                try {
                    $user = $sessionHandler->authenticate($username, $password);
                    if ($user instanceof User) {
                        $authenticated = $sessionHandler->create($user->getUsername());
                        if ($remember) {
                            $this->rememberer->rememberUsername($user->getUsername(), Remember::DEFAULT_EXPIRY);
                        }
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
                    if (
                        $page
                        && (new Uri($page))->isAbsolutePath()
                        && $page !== '/'
                        && $page !== '/login'
                        && $page !== '/login/'
                    ) {
                        $page           = rawurldecode($page);
                        $this->response = $this->redirectSeeOther($page);
                    } else {
                        $this->response = $this->redirectSeeOther('/');
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
        return $this->ipMatches('saml_ip_blacklist');
    }

    /**
     * Check if the users IP exists in the SAML whitelist.
     * @return bool
     */
    private function samlForce(): bool
    {
        return $this->ipMatches('saml_ip_whitelist');
    }

    /**
     * Does this user's IP address exist in the config?
     *
     * @param string $config
     * @return bool
     */
    private function ipMatches(string $config): bool
    {
        $ipList = array_map('trim', explode(',', $this->getConfigLoader()->get($config)));
        return IpUtils::checkIp(Session::getIP(), $ipList);
    }
}
