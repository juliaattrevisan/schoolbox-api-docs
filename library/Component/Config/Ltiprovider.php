<?php
/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Schoolbox\LTI\Provider\Mapper as ProviderMapper;
use Schoolbox\LTI\Provider\Model as ProviderModel;
use Schoolbox\Mapper\Exception as MapperException;

class Component_Config_LTIProvider extends Component_Config_Abstract
{
    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {
        /** @var ProviderMapper $providerMapper */
        $providerMapper = $page->getDi()->get('mapperFactory')->get(\Schoolbox\LTI\Provider\Mapper::class);

        $page->setBlock($keyblock, 'config_provider', 'config_provider_block');

        $providers = $providerMapper->findAll([
            'order' => ['name' => 'asc']
        ]);

        foreach ($providers as $provider) {
            /** @var $provider ProviderModel */
            $page->setVar([
                'PROVIDER_ID' => $provider->getId(),
                'PROVIDER_NAME' => $provider->getName(),
                'PROVIDER_SELECTED' => (int)$configValue === $provider->getId() ? 'selected' : '',
            ]);

            $page->parse('config_provider_block', 'config_provider', true);
        }

    }
}
