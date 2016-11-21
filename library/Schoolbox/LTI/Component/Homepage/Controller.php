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
use IMSGlobal\LTI\ToolProvider as LTI;
use IMSGlobal\LTI\ToolProvider\ToolProvider as LTIToolProvider;
use IMSGlobal\LTI\ToolProvider\Service as LTIService;
use Schoolbox\LTI\Provider\Mapper as ProviderMapper;
use Schoolbox\LTI\Provider\Model as Provider;

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
        $this->providerMapper = $sitepage->getDi()->get('providerMapper');
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
        try {

            // Get LTI provider details from the database

            /** @var Provider $provider */
            $provider = $this->providerMapper->findById($this->getConfig('ltiprovider'));

            $params = [
                'resource_link_id' => $this->getContext()->getFolder()->getId() . "_" . $this->getInstance()->getId(),
                'resource_link_title' => $this->getConfig('componentname'),
                'resource_link_description' => '',
                'user_id' => $this->getUser()->getId(),
                'user_image' => "http://{$_SERVER['SERVER_NAME']}/portrait.php?userId={$this->getUser()->getExternalId()}&size=square80",
                'roles' =>
                    $this->getUser()->getRole()->isStudent() ? 'Student' : (
                    $this->getUser()->getRole()->isStaff() ? 'Instructor' :
                        'Guest'
                    ),
                'lis_person_name_given' => $this->getUser()->getFirstname(),
                'lis_person_name_family' => $this->getUser()->getLastname(),
                'lis_person_name_full' => $this->getUser()->getFullName(),
                'list_person_contact_email_primary' => $this->getUser()->getEmail(),
                'context_id' => $this->getInstance()->getId(),
                'context_type' => 'CourseSection',
                'context_title' => $this->getContext()->getFolder()->getName(),
                'context_label' => $this->getContext()->getFolder()->getCodeString(),
                'launch_presentation_locale' => 'en-AU',
                'launch_presentation_document_target' => 'iframe',
                'launch_presentation_width' => 320,
                'launch_presentation_height' => 240,
                'launch_presentation_return_url' => 'http://192.168.36.10/lti.php',
                'tool_consumer_instance_guid' => 'schoolbox.edu',
                'tool_consumer_instance_name' => 'Schoolbox',
                'tool_consumer_instance_description' => 'Schoolbox LMS',
                'tool_consumer_instance_contact_email' => 'support@alaress.com.au',
            ];

            $consumer = new LTI\ToolConsumer($provider->getKey());
            $consumer->name = $provider->getName();
            $consumer->secret = $provider->getSecret();
            $consumer->enabled = true;
            $consumer->save();

            $consumer->doServiceRequest(
                $service = new LTI\Service\Service($consumer,
                    $provider->getUrl(),
                    'basic-lti-launch-request'
                ),
                'POST',
                '',
                $params
            );

            if (!empty($provider->getSecret())) {
                $params = $consumer->signParameters(
                    $provider->getUrl(),
                    'basic-lti-launch-request',
                    LTIToolProvider::LTI_VERSION1,
                    $params
                );
            }

            $response = $consumer->doServiceRequest($service,
                'POST',
                'basic-lti-launch-request',
                $params
            );

            if ($response->ok) {
                preg_match("/Location: (.*)$/m", $response->responseHeaders, $matches);
                $location = $matches[1];
            } else if ($response->status == 200) {
                $location = $provider->getUrl();
            } else {
                $location = "";
            }

        } catch (MapperException $e) {

            // Could not locate provider in database- most likely its a new component and the provider hasn't been chosen in
            // the component configuration

            $location = "";
        }

        // Render this component out

        return $this->renderTwigTemplate(
            $templateHandle,
            '@lti/component.twig',
            array(
                'session'               => $_SESSION,
                'componentInstanceId'   => $this->getInstance()->getId(),
                'componentClass'        => $this->getComponentName(),
                'location'              => $location,
                'height'                => preg_replace("/[^0-9]/", "", $this->getConfig('pixel'))
            ),
            $this->hasWriteAccess() || !$this->getConfig('compactMode')
        );
    }
}
