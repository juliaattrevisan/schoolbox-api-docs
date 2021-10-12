<?php

/**
 * Lists admin page
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\LTI;

use Alaress\Web\Controller\Base;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\LTI\Provider\Model as Provider;
use Schoolbox\LTI\Provider\Mapper as ProviderMapper;

class Controller extends Base
{
    /**
     * Provider mapper, for fetching and saving LTI providers.
     */
    private ProviderMapper $providerMapper;

    /**
     * Controller constructor.
     * @param ProviderMapper $providerMapper
     */
    public function __construct(ProviderMapper $providerMapper)
    {
        $this->providerMapper = $providerMapper;
    }

    /**
     * Create a new LTI provider
     */
    public function getInsert()
    {
        // Render this thing out

        $this->getResponse()->content->set([
            'template' => '@adminLti/modify.twig',
            'data'     => [
                'pageCurrent' => $this->router->generate('adminLti.getIndex'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('adminLti.getIndex')),
            ],
        ]);
    }

    /**
     *
     */
    public function getIndex()
    {
        // Render this thing out

        $this->getResponse()->content->set([
            'template' => '@adminLti/index.twig',
            'data'     => [
                'pageCurrent' => $this->router->generate('adminLti.getIndex'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('adminLti.getIndex')),
                'providers' => $this->providerMapper->findAll(),
                'providerLtiVersion11' => Provider::LTI_VERSION_1_1,
                'providerLtiVersion13' => Provider::LTI_VERSION_1_3,
            ],
        ]);
    }

    /**
     * Modify an existing LTI provider
     *
     * @param Provider $provider
     */
    public function getModify($provider)
    {
        // Render this thing out
        $this->getResponse()->content->set([
            'template' => '@adminLti/modify.twig',
            'data'     => [
                'pageCurrent' => $this->router->generate('adminLti.getIndex'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('adminLti.getIndex')),
                'provider' => $provider
            ],
        ]);
    }

    public function postDelete($provider)
    {
        if (!empty($provider)) {
            $this->providerMapper->delete($provider);
        }

        $this->getResponse()->redirect->afterPost($this->getRouter()->generate('adminLti.getIndex'));
    }

    public function postModify()
    {
        $post = $this->request->post;

        if (!empty($post['id'])) {
            $provider = $this->providerMapper->findById($post['id']);
        } else {
            $provider = new Provider();
        }

        $provider->setName($post['name']);
        $provider->setLtiVersion($post['lti_version']);

        // LTI 1.3 fields
        $provider = $provider
            ->setClientId($post['client_id'])
            ->setAudience($post['audience'])
            ->setOidcInitiationUrl($post['oidc_initiation_url'])
            ->setLaunchUrl($post['launch_url'])
            ->setDeepLinkingUrl($post['deep_linking_url'])
            ->setJwksUrl($post['jwks_url'])
            ->setPublicKey($post['public_key']);

        // LTI 1.1 fields, including XML
        $provider = $provider
            ->setCustom(null)
            ->setKey($post['key'])
            ->setSecret($post['secret'])
            ->setXml($post['xml'])
            ->setLaunchType($post['launch_type'])
            ->setIcon($post['icon'])
            ->setUrl($post['url']);
        if ($post['xml']) {
            try {
                $xmlContent = @file_get_contents($post['xml']);

                if ($xmlContent) {
                    libxml_use_internal_errors(true);

                    $xml = simplexml_load_string($xmlContent);


                    if ($xml !== false) {
                        $launchUrl = $xml->children('blti', true)->secure_launch_url;

                        if (!empty($launchUrl)) {
                            $provider->setUrl((string)$launchUrl);
                        } else {
                            $launchUrl = $xml->children('blti', true)->launch_url;

                            if (!empty($launchUrl)) {
                                $provider->setUrl((string)$launchUrl);
                            }
                        }

                        $icon = $xml->children('blti', true)->secure_icon;

                        if (!empty($launchUrl)) {
                            $provider->setIcon((string)$icon);
                        }
                    } else {
                        $provider->setUrl($post['xml']);
                    }
                }
            } catch (\Exception $e) {
                // Noop
            }
        }

        $this->providerMapper->save($provider);

        $this->response->redirect->afterPost($this->router->generate('adminLti.getIndex'));
    }
}
