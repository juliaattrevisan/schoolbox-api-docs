<?php
/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Schoolbox\LTI\Provider\Mapper as ProviderMapper;
use Schoolbox\LTI\Provider\Model as Provider;

class Component_Config_LTIProvider extends Component_Config_Abstract
{
    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {
        /** @var ProviderMapper $providerMapper */
        $providerMapper = $page->getDi()->get('providerMapper');
        $providers = $providerMapper->findAll()->sortBy('name', 'asc');

        $page->setBlock($keyblock, 'providers', 'providers_block');
        $page->setVar('providers_block', '');

        /** @var Provider $provider */
        foreach ($providers as $provider) {
            if (!empty($provider->getUrl())) {
                $page->setVar('COMPONENT_LTI_PROVIDER_ID', $provider->getId());
                $page->setVar('COMPONENT_LTI_PROVIDER_NAME', $provider->getName());
                $page->setVar('COMPONENT_LTI_PROVIDER_SELECTED', $provider->getId() == $configValue ? 'selected' : '');
                $page->parse('providers_block', 'providers', true);
            }
        }

    }
}
