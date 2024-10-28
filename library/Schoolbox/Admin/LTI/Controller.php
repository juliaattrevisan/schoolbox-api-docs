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
use Alaress\Module\Cache\Rebuilder;
use Alaress\Web\Controller\ConfigController;
use Aura\Router\Exception\RouteNotFound;
use Psr\SimpleCache\CacheInterface;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Admin\Settings\FieldStrategyFactory;
use Schoolbox\Admin\Settings\ModuleConfig;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\GenericCollection;
use Schoolbox\LTI\Provider;
use Schoolbox\LTI\Provider\Mapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Url\Factory;

class Controller extends ConfigController
{
    /**
     * Constructor
     *
     * @param ConfigMapper                $configMapper
     * @param FieldStrategyFactory        $fieldStrategyFactory
     * @param Rebuilder                   $rebuilder
     * @param CacheInterface              $oidcCache
     * @param Mapper                      $providerMapper       Provider mapper, for fetching and saving LTI providers.
     * @param Provider\RedirectUrl\Mapper $redirectUrlMapper    Redirect URL mapper, for saving provider redirect URLs.
     */
    public function __construct(
        ConfigMapper $configMapper,
        FieldStrategyFactory $fieldStrategyFactory,
        Rebuilder $rebuilder,
        CacheInterface $oidcCache,
        private Provider\Mapper $providerMapper,
        private Provider\RedirectUrl\Mapper $redirectUrlMapper,
    ) {
        parent::__construct($configMapper, $fieldStrategyFactory, $rebuilder, $oidcCache);
    }

    /**
     * Create a new LTI provider
     */
    public function getInsert()
    {
        // Render this thing out
        $this->response = $this->responseFromTemplate(
            '@adminLti/modify.twig',
            [
                'pageCurrent'    => $this->routeGenerator->generate('adminLti.getIndex'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminLti.getIndex')),
            ],
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
            $this->routeGenerator->generate('adminLti.postConfig'),
            [ModuleConfig::CONTEXT_USE_BOTTOM_DRAWER => false,],
        );

        $baseUrl = (new Factory($this->getConfigLoader()))->buildFullyQualified();
        $this->response = $this->responseFromTemplate(
            '@adminLti/index.twig',
            [
                'pageCurrent'           => $this->routeGenerator->generate('adminLti.getIndex'),
                'allBreadcrumbs'        => (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminLti.getIndex')),
                'providers'             => $this->providerMapper->findAll(),
                'providerLtiVersion11'  => Provider\Model::LTI_VERSION_1_1,
                'providerLtiVersion13'  => Provider\Model::LTI_VERSION_1_3,
                'providerTargetIframe'  => Provider\Model::TARGET_IFRAME,
                'providerTargetWindow'  => Provider\Model::TARGET_WINDOW,
                'configContext'         => $configContext,
                'issuer'                => (string)$baseUrl,
                'oidcAuthenticationUrl' => (string)(clone $baseUrl)->withPath($this->routeGenerator->generate('ltiPlatform.getOidcAuthenticationRequest')),
                'oauthAccessTokenUrl'   => (string)(clone $baseUrl)->withPath($this->routeGenerator->generate('ltiPlatform.getOauthAccessTokenRequest')),
                'jsonWebKeySetUrl'      => (string)(clone $baseUrl)->withPath($this->routeGenerator->generate('ltiPlatform.getJsonWebKeySet')),
            ],
        );
    }

    /**
     * Modify an existing LTI provider
     *
     * @param Provider\Model $provider
     * @throws RouteNotFound
     */
    public function getModify(Provider\Model $provider): void
    {
        // Render this thing out
        $this->response = $this->responseFromTemplate(
            '@adminLti/modify.twig',
            [
                'pageCurrent'    => $this->routeGenerator->generate('adminLti.getIndex'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminLti.getIndex')),
                'provider'       => $provider,
            ],
        );
    }

    /**
     * Delete an existing LTI provider
     *
     * @param Provider\Model $provider
     * @throws RouteNotFound
     * @throws MapperException
     */
    public function postDelete(Provider\Model $provider): void
    {
        $this->providerMapper->delete($provider);
        $this->response = $this->redirectAfterPost($this->routeGenerator->generate('adminLti.getIndex'));
    }

    /**
     * Update an existing LTI provider
     *
     * @throws MapperException
     * @throws RouteNotFound
     */
    public function postModify(): void
    {
        $post = $this->requestPost;

        if (!empty($post['id'])) {
            $provider = $this->providerMapper->findById($post['id']);
        } else {
            $provider = new Provider\Model();
            $provider->setLtiVersion($post['lti_version']);
        }

        // Common fields
        $provider = $provider->setName($post['name']);

        // LTI 1.3 fields, including redirect URLs
        $provider = $provider
            ->setClientId(null)
            ->setAudience(null)
            ->setOidcInitiationUrl($post['oidc_initiation_url'])
            ->setLaunchUrl($post['launch_url'])
            ->setDeepLinkingUrl($post['deep_linking_url'])
            ->setJwksUrl($post['jwks_url'])
            ->setPublicKey($post['public_key'])
            ->setAvailableForComponent(
                $provider->isLtiVersion11()
                    ? true
                    : $post->getBool('available_for_component', false)
            )
            ->setAvailableForAssessment(
                $provider->isLtiVersion11()
                    ? false
                    : $post->getBool('available_for_assessment', false)
            );
        if ($provider->isLtiVersion13()) {
            $provider = $provider->setTarget($post['target1p3']);
        }

        // Add and remove redirect URLs
        $existingRedirectUrls = $provider->getRedirectUrls();
        $newRedirectUrls      = $this->redirectUrlMapper->createCollection();
        foreach (($post['redirect_urls'] ?? []) as $data) {
            $idValue          = (int)($data['id'] ?? '');
            $redirectUrlValue = $data['redirect_url'] ?? '';
            if ($redirectUrlValue !== '') {
                $newRedirectUrlModel = $this->redirectUrlMapper->createModel()
                    ->setLtiProvider($provider)
                    ->setRedirectUrl($redirectUrlValue);
                if ($idValue > 0) {
                    $newRedirectUrlModel = $newRedirectUrlModel->setId($idValue);
                }
                $newRedirectUrls->add($newRedirectUrlModel);
            }
        }

        // LTI 1.1 fields, including XML
        $provider = $provider
            ->setCustom(null)
            ->setKey($post['key'])
            ->setSecret($post['secret'])
            ->setXml($post['xml'])
            ->setLaunchType($post['launch_type'])
            ->setIcon($post['icon'])
            ->setUrl($post['url']);
        if ($provider->isLtiVersion11()) {
            $provider = $provider->setTarget($post['target1p1']);
        }
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

        $this->redirectUrlMapper->delete($existingRedirectUrls->diff($newRedirectUrls));
        $this->redirectUrlMapper->save($newRedirectUrls);

        $this->response = $this->redirectAfterPost($this->routeGenerator->generate('adminLti.getIndex'));
    }

    /**
     * Save settings
     *
     * @throws RouteNotFound
     * @throws MapperException
     */
    public function postConfig(): void
    {
        $this->updateConfig($this->requestPost->getArrayCopy(), $this->buildConfigPredicate());
        $this->response = $this->redirectAfterPost($this->routeGenerator->generate('adminLti.getIndex'));
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
