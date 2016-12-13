<?php
/**
 * Homepage blog component controller. Is responsible for rendering the TWIG into component
 * on homepage pages
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\LTI\Component\Homepage;

use Component_Homepage_ControllerAbstract;
use Component_Instance;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use SitePage;
use Schoolbox\LTI\Provider\Mapper as ProviderMapper;

class Controller extends Component_Homepage_ControllerAbstract
{
    /** @var ProviderMapper */
    private $providerMapper;

    /**
     * Injectable constructor
     *
     * @param Component_Instance $instance
     * @param User               $user
     * @param SitePage           $sitepage
     */
    public function __construct(Component_Instance $instance, User $user, SitePage $sitepage) {
        parent::__construct($instance, $user, $sitepage);
        $this->providerMapper = $sitepage->getDi()->get('mapperFactory')->get(ProviderMapper::class);
    }

    /**
     * Loads the grid component into its set configuration.
     *
     * @return true
     */
    public function execute()
    {
        return true;
    }

    /**
     * @param String $templateHandle
     * @return bool
     */
    public function renderTemplate($templateHandle)
    {
        $providers = $this->providerMapper->findAll([
            'order' => ['name' => 'asc']
        ]);

        // Render launcher form

        return $this->renderTwigTemplate(
            $templateHandle,
            '@lti/component.twig',
            array(
                'session'               => $_SESSION,
                'componentInstanceId'   => $this->getInstance()->getId(),
                'componentClass'        => $this->getComponentName(),
                'providerId'            => $this->getConfig('ltiprovider'),
                'providers'             => $providers,
                'folderId'              => $this->getContext()->getId(),
                'height'                => preg_replace("/[^0-9]/", "", $this->getConfig('pixel'))
            ),
            $this->hasWriteAccess() || !$this->getConfig('compactMode')
        );
    }
}
