<?php
/**
 * LTI homepage component
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\LTI\Component\Homepage;

use Component_Homepage_ControllerAbstract;
use Component_Instance;
use Schoolbox\LTI\Provider\Mapper as ProviderMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;
use SitePage;

class Controller extends Component_Homepage_ControllerAbstract
{
    /** @var ProviderMapper */
    private $providerMapper;

    /**
     * Injectable constructor
     *
     * @param Component_Instance $instance
     * @param User $user
     * @param SitePage $sitepage
     */
    public function __construct(Component_Instance $instance, User $user, SitePage $sitepage)
    {
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
     * {@inheritdoc}
     *
     * @param String $templateHandle
     * @return bool
     */
    public function renderTemplate($templateHandle)
    {
        // Retrieve list of all LTI providers from database

        $providers = $this->providerMapper->findAll([
            'order' => ['name' => 'asc']
        ]);

        // Read-only users see nothing at all if no LTI providers exist,
        // or none is set up for this component.
        $providerId = $this->getConfig('ltiprovider');
        $readOnly   = $this->getContentPermission() < UserAccess::ACCESS_WRITE;

        if ($readOnly) {

            /* No provider selected, or no providers exist
             * Don't render the component
             */
            if (!$providerId || $providers->count() === 0) {
                return false;
            }

            /* A provider was selected, but it might be deleted or otherwise nonexistent
             * Don't render the component
             */
            try {
                $this->providerMapper->findById($providerId);
            } catch (\Schoolbox\Mapper\Exception $e) {
                return false;
            }
        }

        // Render launcher form
        return $this->renderTwigTemplate(
            $templateHandle,
            '@lti/component.twig',
            [
                'componentInstanceId' => $this->getInstance()->getId(),
                'providerId' => $this->getConfig('ltiprovider'),
                'providers' => $providers,
                'folderId' => $this->getContext()->getId(),
                'height' => preg_replace("/[^0-9]/", "", $this->getConfig('pixel')),
                'user' => $this->getUser()
            ]
        );
    }
}
