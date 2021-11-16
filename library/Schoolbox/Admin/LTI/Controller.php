<?php

/**
 * Lists admin page
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\LTI;

use Alaress\Database\Predicate\Builder as PredicateBuilder;
use Alaress\Web\Controller\ConfigController;
use Aura\Router\Exception\RouteNotFound;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Admin\Settings\FieldStrategyFactory;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\LTI\Provider\Model as Provider;
use Schoolbox\LTI\Provider\Mapper as ProviderMapper;
use Schoolbox\Mapper\Exception as MapperException;

class Controller extends ConfigController
{
    /**
     * Provider mapper, for fetching and saving LTI providers.
     */
    private ProviderMapper $providerMapper;

    /**
     * Controller constructor.
     * @param ConfigMapper         $configMapper
     * @param FieldStrategyFactory $fieldStrategyFactory
     * @param ProviderMapper       $providerMapper
     */
    public function __construct(
        ConfigMapper $configMapper,
        FieldStrategyFactory $fieldStrategyFactory,
        ProviderMapper $providerMapper
    ) {
        parent::__construct($configMapper, $fieldStrategyFactory);

        $this->providerMapper = $providerMapper;
    }

    /**
     * Create a new LTI provider
     */
    public function getInsert()
    {
        // Render this thing out

        $this->getResponse()->content->set(
            [
                'template' => '@adminLti/modify.twig',
                'data'     => [
                    'pageCurrent'    => $this->router->generate('adminLti.getIndex'),
                    'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('adminLti.getIndex')),
                ],
            ]
        );
    }

    /**
     * LMS > LTI Providers page
     * @throws RouteNotFound
     */
    public function getIndex(): void
    {
        $configContext = $this->buildConfigContext(
            $this->buildConfigPredicate(),
            $this->router->generate('adminLti.postConfig')
        );

        $this->getResponse()->content->set(
            [
                'template' => '@adminLti/index.twig',
                'data'     => [
                    'pageCurrent'          => $this->router->generate('adminLti.getIndex'),
                    'allBreadcrumbs'       => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('adminLti.getIndex')),
                    'providers'            => $this->providerMapper->findAll(),
                    'providerLtiVersion11' => Provider::LTI_VERSION_1_1,
                    'providerLtiVersion13' => Provider::LTI_VERSION_1_3,
                    'configContext'        => $configContext,
                ],
            ]
        );
    }

    /**
     * Modify an existing LTI provider
     *
     * @param Provider $provider
     * @throws RouteNotFound
     */
    public function getModify(Provider $provider): void
    {
        // Render this thing out
        $this->getResponse()->content->set(
            [
                'template' => '@adminLti/modify.twig',
                'data'     => [
                    'pageCurrent'    => $this->router->generate('adminLti.getIndex'),
                    'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('adminLti.getIndex')),
                    'provider'       => $provider,
                ],
            ]
        );
    }

    /**
     * Delete an existing LTI provider
     *
     * @param Provider $provider
     * @throws RouteNotFound
     * @throws MapperException
     */
    public function postDelete(Provider $provider): void
    {
        if (!empty($provider)) {
            $this->providerMapper->delete($provider);
        }

        $this->getResponse()->redirect->afterPost($this->getRouter()->generate('adminLti.getIndex'));
    }

    /**
     * Update an existing LTI provider
     *
     * @throws MapperException
     * @throws RouteNotFound
     */
    public function postModify(): void
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
            ->setClientId(null)
            ->setAudience(null)
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

    /**
     * Save settings
     *
     * @throws RouteNotFound
     * @throws MapperException
     */
    public function postConfig(): void
    {
        $this->updateConfig($this->request->post->get(), $this->buildConfigPredicate());
        $this->response->redirect->afterPost($this->router->generate('adminLti.getIndex'));
    }

    /**
     * Returns a predicate builder for finding all custom config for this module
     *
     * @return PredicateBuilder
     */
    private function buildConfigPredicate(): PredicateBuilder
    {
        return $this->getConfigMapper()->getPredicateBuilder()->equals(ConfigMapper::COL_MODULE, ConfigMapper::MODULE_LTI);
    }
}
