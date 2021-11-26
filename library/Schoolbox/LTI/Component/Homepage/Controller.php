<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\LTI\Component\Homepage;

use Component_Homepage_ControllerAbstract;
use Component_Instance;
use OAT\Library\Lti1p3Core\Message\LtiMessageInterface;
use Schoolbox\LTI\Provider\Mapper as ProviderMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;
use SitePage;

/**
 * LTI homepage component
 */
class Controller extends Component_Homepage_ControllerAbstract
{
    /**
     * Component config: title
     */
    public const COMPONENT_CONFIG_TITLE = 'componentname';

    /**
     * Component config: LTI provider ID
     */
    public const COMPONENT_CONFIG_LTI_PROVIDER = 'ltiprovider';

    /**
     * Component config: embed URL
     */
    public const COMPONENT_CONFIG_LTI_EMBED_URL = 'url';

    /**
     * Component config: description
     */
    public const COMPONENT_CONFIG_LTI_CUSTOM_PARAMETERS = 'description';

    /**
     * Component config: height
     */
    public const COMPONENT_CONFIG_HEIGHT = 'pixel';

    /**
     * LTI tool (f.k.a. provider) mapper, for fetching the tool to use to
     * display this component's content.
     */
    private ProviderMapper $providerMapper;

    /**
     * Injectable constructor
     *
     * @param Component_Instance $instance
     * @param User               $user
     * @param SitePage           $sitepage
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
        // Retrieve list of LTI providers usable in components from database
        $providers = $this->providerMapper->findAllAvailableForComponent();

        // Read-only users see nothing at all if no LTI providers exist,
        // or none is set up for this component.
        $providerId = $this->getConfig('ltiprovider');
        $readOnly   = $this->getContentPermission() < UserAccess::ACCESS_WRITE;

        /* A provider was selected, but it might be deleted or otherwise nonexistent
         * Don't render the component
         *
         * If you're a staff member, you'll get an error screen from a nonexistent provider
         * but we check here, so we can set the iframe height accordingly
         */
        $provider = null;

        if ($providerId) {
            try {
                $provider = $this->providerMapper->findById($providerId);
            } catch (\Schoolbox\Mapper\Exception $e) {
                // noop
            }
        }

        /* No provider selected, selected provider is nonexistent, or no providers exist
         * Don't render the component
         */
        if ($readOnly && (!$providerId || !$provider || $providers->count() === 0)) {
            return false;
        }

        // Render launcher form
        return $this->renderTwigTemplate(
            $templateHandle,
            '@lti/component.twig',
            [
                'componentInstanceId'            => $this->getInstance()->getId(),
                'providerId'                     => $this->getConfig(self::COMPONENT_CONFIG_LTI_PROVIDER),
                'embedUrl'                       => $this->getConfig(self::COMPONENT_CONFIG_LTI_EMBED_URL),
                'providers'                      => $providers,
                'provider'                       => $provider,
                'folderId'                       => $this->getContext()->getId(),
                'height'                         => preg_replace(
                    "/[^0-9]/",
                    "",
                    $this->getConfig(self::COMPONENT_CONFIG_HEIGHT),
                ),
                'user'                           => $this->getUser(),
                'deepLinkingResponseMessageType' => LtiMessageInterface::LTI_MESSAGE_TYPE_DEEP_LINKING_RESPONSE,
            ]
        );
    }
}
