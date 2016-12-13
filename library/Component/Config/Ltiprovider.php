<?php
/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Schoolbox\LTI\Provider\Mapper as ProviderMapper;
use Schoolbox\Mapper\Exception as MapperException;

class Component_Config_LTIProvider extends Component_Config_Abstract
{
    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {
        /** @var ProviderMapper $providerMapper */
        $providerMapper = $page->getDi()->get('mapperFactory')->get(\Schoolbox\LTI\Provider\Mapper::class);

        try {
            $provider = $providerMapper->findById($configValue);
            $page->setVar('COMPONENT_LTI_PROVIDER_NAME', Scrub::text($provider->getName()));
            $page->setVar('COMPONENT_LTI_PROVIDER_ID', $configValue);
        } catch (MapperException $e) {
            $page->setVar('COMPONENT_LTI_PROVIDER_NAME', 'None Selected');
        }

    }
}
