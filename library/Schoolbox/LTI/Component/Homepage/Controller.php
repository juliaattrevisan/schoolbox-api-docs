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
use Schoolbox\IConfigLoader;
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

    /** @var IConfigLoader */
    private $configLoader;

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
        $this->configLoader = $sitepage->getDi()->get('config');
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


            // LearningField extraction from the custom field into the URL and parameters

            if (strtolower($provider->getUrl()) == 'https://bc.vitalsource.com/books/book') {
                $custom = $this->getConfig('description');
                preg_match('/\/books\/(.*?)\//i', $custom, $matches);
                $params['custom_vbid'] = $matches[1];
                preg_match('/\/pageid\/(.*)/i', $custom, $matches);
                $params['custom_book_location'] = 'pageid/' . $matches[1];

                $provider->setUrl($provider->getUrl() . "?custom_vbid={$params['custom_vbid']}&custom_book_location={$params['custom_book_location']}");
            }

            $params = [
                'resource_link_id' => $this->getInstance()->getId(),
                'resource_link_title' => $this->getConfig('componentname'),
                'resource_link_description' => '',
                'user_id' => $this->getUser()->getId(),
                'user_image' => urlencode("http://{$_SERVER['SERVER_NAME']}/portrait.php?userId={$this->getUser()->getExternalId()}&size=square80"),

                // Students get a 'Student' role, staff get an 'Instructor' role, everyone else gets 'Guest' role

                'roles' =>
                    $this->getUser()->getRole()->isStudent() ? 'Student' : (
                    $this->getUser()->getRole()->isStaff() ? 'Instructor' :
                        'Guest'
                    ),
                'lis_person_name_given' => $this->getUser()->getFirstname(),
                'lis_person_name_family' => $this->getUser()->getLastname(),
                'lis_person_name_full' => $this->getUser()->getFullName(),
                'lis_person_contact_email_primary' => $this->getUser()->getEmail(),
                'context_id' => $this->getInstance()->getId(),
                'context_type' => 'CourseSection',
                'context_title' => $this->getContext()->getFolder()->getName(),
                'context_label' => $this->getContext()->getFolder()->getCodeString(),
                'launch_presentation_locale' => 'en-US',
                'launch_presentation_document_target' => 'iframe',
                'launch_presentation_width' => '800',
                'launch_presentation_height' => preg_replace("/[^0-9]/", "", $this->getConfig('pixel')),

                // TODO: Need to handle return GET request from tool providers
                'launch_presentation_return_url' => urlencode('#'),

                'tool_consumer_instance_guid' => $this->configLoader->get('site_location'),
                'tool_consumer_instance_name' => $this->configLoader->get('school_name'),
                'tool_consumer_instance_description' => 'Schoolbox LMS',
                'tool_consumer_instance_url' => urlencode("http://" . $_SERVER['SERVER_NAME']),
                'tool_consumer_instance_contact_email' => $this->configLoader->get('mail_from_address'),
                'tool_consumer_info_product_family_code' => 'schoolbox',
                'tool_consumer_info_version' => $this->configLoader->get('site_version'),
                'ext_lms' => 'schoolbox-' . $this->configLoader->get('site_version')
            ];

            $consumer = new LTI\ToolConsumer($provider->getKey());
            $consumer->name = $provider->getName();
            $consumer->secret = $provider->getSecret();
            $consumer->enabled = true;
            $consumer->save();

            $service = new LTI\Service\Service(
                $consumer,
                $provider->getUrl(),
                'basic-lti-launch-request'
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

            if ($response->ok || $response->status == 301 || $response->status == 302 || $response->status == 303) {

                preg_match("/Location: (.*)$/m", $response->responseHeaders, $matches);
                $location = $matches[1];

                // OneNote redirection does not have an absolute path
                if ($provider->getName() == 'OneNote Class Notebook') {
                    $location = 'http://www.onenote.com' . $location;
                }

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
