<?php

/**
 * LTI v1.2 consumer implementation
 *
 * Implementation guide can be found at: https://www.imsglobal.org/specs/ltiv1p2/implementation-guide
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\LTI\Consumer;

use Alaress\Web\Controller\Base;
use Aura\Router\Exception\RouteNotFound as AuraRouteNotFound;
use Component_Config;
use Component_ConfigDefinitionMapper;
use Component_ConfigMapper;
use Component_ControllerAbstract;
use DateTime;
use Exception;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\LTI\Component\Homepage\Controller as LtiComponent;
use Schoolbox\LTI\Provider\Model as Provider;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\User;

class Controller extends Base
{
    // ---------------------------------------------------------------------------------------------------
    // Constants
    // ---------------------------------------------------------------------------------------------------

    /**
     * LTI version constants
     */

    const LTI_VERSION_1 = 'LTI-1p0';
    const LTI_VERSION_2 = 'LTI-2p0';

    /**
     * LTI request types
     */

    const LTI_LAUNCH_TYPE_BASIC = 'basic-lti-launch-request';
    const LTI_LAUNCH_TYPE_PICKER = 'ContentItemSelectionRequest';

    /**
     * LTI contexts
     */
    const LTI_CONTEXT_CONTENT_ITEM = 'http://purl.imsglobal.org/ctx/lti/v1/ContentItem';

    /**
     * LTI media types
     */
    const LTI_MEDIA_TYPE_LINK = 'application/vnd.ims.lti.v1.ltilink';

    /**
     * Component config: title
     *
     * @deprecated moved to `Schoolbox\LTI\Component\Homepage\Controller`
     */
    const COMPONENT_CONFIG_TITLE = 'componentname';

    /**
     * Component config: LTI provider ID
     *
     * @deprecated moved to `Schoolbox\LTI\Component\Homepage\Controller`
     */
    const COMPONENT_CONFIG_LTI_PROVIDER = 'ltiprovider';

    /**
     * Component config: embed URL
     *
     * @deprecated moved to `Schoolbox\LTI\Component\Homepage\Controller`
     */
    const COMPONENT_CONFIG_LTI_EMBED_URL = 'url';

    /**
     * Component config: description
     *
     * @deprecated moved to `Schoolbox\LTI\Component\Homepage\Controller`
     */
    const COMPONENT_CONFIG_LTI_CUSTOM_PARAMETERS = 'description';

    /**
     * Component config: height
     *
     * @deprecated moved to `Schoolbox\LTI\Component\Homepage\Controller`
     */
    const COMPONENT_CONFIG_HEIGHT = 'pixel';

    /**
     * Allowed LTI return types
     *
     * This is not part of the core LTI specification. Further documentation can be found here:
     * https://www.edu-apps.org/extensions/content.html
     *
     * OEmbed has been intentionally skipped for now as its a pain in the butt to implement and I
     * don't currently have any examples of LTI providers that use this
     */
    const LTI_RETURN_TYPE_IFRAME = 'iframe';
    const LTI_RETURN_TYPE_URL = 'url';
    const LTI_RETURN_TYPE_FILE = 'file';
    const LTI_RETURN_TYPE_IMAGE = 'image_url';
    const LTI_RETURN_TYPE_LAUNCH = 'lti_launch_url';

    /**
     * Allowed LTI user roles
     */
    const LTI_ROLE_STUDENT = 'Student';
    const LTI_ROLE_INSTRUCTOR = 'Instructor';
    const LTI_ROLE_GUEST = 'Guest';

    // ---------------------------------------------------------------------------------------------------
    // Variables
    // ---------------------------------------------------------------------------------------------------

    /** @var Component_ConfigDefinitionMapper $componentConfigDefinitionMapper */
    private $componentConfigDefinitionMapper;

    /** @var Component_ConfigMapper $componentConfigMapper */
    private $componentConfigMapper;

    /** @var string */
    private $baseUrl;

    /** @var User */
    private $user;

    /**
     * Controller constructor
     */
    public function __construct()
    {
        $this->componentConfigMapper           = Component_ConfigMapper::getInstance();
        $this->componentConfigDefinitionMapper = Component_ConfigDefinitionMapper::getInstance();
        $serverHost                            = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'];
        $this->baseUrl                         = "{$_SERVER['REQUEST_SCHEME']}://{$serverHost}";
    }

    /**
     * Silently update component configuration and set the provider it will use.
     *
     * Works for both LTI 1.1 and 1.3 providers:
     * * LTI 1.1 will just set the provider and title
     * * LTI 1.3 basic launch will set the provider, title and URL
     * * LTI 1.3 picker will just set the provider and title
     *
     * @param Provider                     $provider
     * @param Component_ControllerAbstract $component
     *
     * @throws MapperException
     * @throws Exception
     */
    public function postComponentProvider($provider, $component)
    {
        $post = $this->getRequest()->post;

        $this->updateComponentValue(
            $component,
            LtiComponent::COMPONENT_CONFIG_LTI_PROVIDER,
            $provider->getId(),
        );
        $this->updateComponentValue(
            $component,
            LtiComponent::COMPONENT_CONFIG_TITLE,
            $post[LtiComponent::COMPONENT_CONFIG_TITLE] ?? $provider->getName(),
        );

        if (isset($post[LtiComponent::COMPONENT_CONFIG_LTI_EMBED_URL])) {
            $this->updateComponentValue(
                $component,
                LtiComponent::COMPONENT_CONFIG_LTI_EMBED_URL,
                $post[LtiComponent::COMPONENT_CONFIG_LTI_EMBED_URL],
            );
        }
        if (isset($post[LtiComponent::COMPONENT_CONFIG_LTI_CUSTOM_PARAMETERS])) {
            $this->updateComponentValue(
                $component,
                LtiComponent::COMPONENT_CONFIG_LTI_CUSTOM_PARAMETERS,
                $post[LtiComponent::COMPONENT_CONFIG_LTI_CUSTOM_PARAMETERS],
            );
        }

        $this->getResponse()->content->set([
            'format' => 'json',
            'data'   => [
                'result'       => 'OK',
                'providerName' => $provider->getName(),
                'url'          => $post['url'] ?? null,
            ],
        ]);
    }

    /**
     * Parse the result of an LTI resource picker and update the component with the appropriate embed URL
     *
     * @param Component_ControllerAbstract $component
     *
     * @throws MapperException
     * @throws Exception
     */
    public function postComponentPicker($component)
    {
        $post = $this->getRequest()->post;

        // Make sure we got content items back from the provider

        if (!isset($post['content_items'])) {
            throw new Exception("No content items returned");
        }

        // Try to decode the content items

        $contentItemsArray = json_decode($post['content_items'], true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Error decoding content items array");
        }

        // Check the context is correct

        if ($contentItemsArray['@context'] !== self::LTI_CONTEXT_CONTENT_ITEM) {
            throw new Exception("Unexpected context received");
        }

        // Check we received a content graph

        if (!isset($contentItemsArray['@graph'])) {
            throw new Exception("Content graph not found");
        }

        // Go through each item in the graph

        foreach ($contentItemsArray['@graph'] as $item) {
            // Grab the title if there is one

            if (isset($item['title'])) {
                $title = $item['title'];
            }

            // The great thing about the LTI standard is that it seems kinda half finished so each vendor
            // has hacked int heir own way of passing back the required information- so we have to manually parse
            // each vendors specific data to find something we can actually use. Yay for "standards"!

            // ---------------------------------------------------------------------------------------------------
            // Education Perfect picker result
            // ---------------------------------------------------------------------------------------------------

            // The target URL we need is in the syntax:
            // {contentUrl}#/{activity}/{module}/activity-starter
            //
            // {
            //   "@context": "http://purl.imsglobal.org/ctx/lti/v1/ContentItem",
            //   "@graph": [
            //     {
            //       "@type": "LtiLinkItem",
            //       "mediaType": "application/vnd.ims.lti.v1.ltilink",
            //       "placementAdvice": {
            //         "presentationDocumentTarget": "frame"
            //       },
            //       "text": "Happy Pi Day! (Year 5-10) (Education Perfect)",
            //       "title": "Happy Pi Day! (Year 5-10) (Education Perfect)",    <--- Component heading
            //       "custom": {
            //         "contentUrl": "https://www.educationperfect.com:443/app/", <--- Target URL
            //         "activity": "2551018",                                     <--- Target URL parameter
            //         "module": "1719905"                                        <--- Target URL parameter
            //       }
            //     }
            //   ]
            // }

            if (isset($item['@type']) && isset($item['mediaType'])) {
                if ($item['@type'] === 'LtiLinkItem' && $item['mediaType'] === self::LTI_MEDIA_TYPE_LINK) {
                    // Handle the EducationPerfect special case
                    if (isset($item['custom'])) {
                        if (
                            isset($item['custom']['contentUrl']) &&
                            isset($item['custom']['activity']) &&
                            isset($item['custom']['module'])
                        ) {
                            // Construct the URL

                            $embedUrl = $item['custom']['contentUrl'] . "#/";
                            $embedUrl .= $item['custom']['activity'] . "/";
                            $embedUrl .= $item['custom']['module'] . "/activity-starter";

                            // Found everything we need, get outta here and use it

                            break;
                        }
                    }

                    // Handle the standard case
                    // (as returned by Google Assignments and probably others)
                    if (isset($item['url'])) {
                        $embedUrl = $item['url'];
                        break;
                    }
                }
            }

            // Check other provider responses... (none yet)
        }

        // If we got here without finding and URL, throw an exception because there is nothing else we can do

        if (!isset($embedUrl) || strlen(trim($embedUrl)) === 0) {
            throw new Exception("No content location header could be found, cannot proceed.");
        }

        // Update the component with the target URL

        $this->updateComponentValue($component, LtiComponent::COMPONENT_CONFIG_LTI_EMBED_URL, $embedUrl);

        // Update the component with the title (assuming we found one)

        if (isset($title)) {
            if (strlen(trim($title)) > 0) {
                $this->updateComponentValue($component, LtiComponent::COMPONENT_CONFIG_TITLE, $title);
            }
        } else {
            $title = $component->getConfig(LtiComponent::COMPONENT_CONFIG_TITLE);
        }

        // Redirect to the target URL

        // Rather than just redirecting the iframe directly to the requested resource we need to do a bit
        // of javascript shenanigans.
        //
        // Because this code is operating inside the LTI iframe, the component on the homepage will not have
        // has its title or iframe height adjusted to the new parameters until the page is reloaded. The
        // javascript inside this template updates these in the DOM.

        // The launch URL generates a POST request to the embed URL.
        $launchUrl = $this->router->generate('ltiConsumer.getLaunch', [
            'providerId'  => $component->getConfig(LtiComponent::COMPONENT_CONFIG_LTI_PROVIDER),
            'componentId' => $component->getInstance()->getId(),
            'folderId'    => $component->getContext()->getId(),
        ]);

        $this->getResponse()->content->set([
            'template' => '@ltiConsumer/redirect.twig',
            'format'   => 'nochrome',
            'data'     => [
                'componentId'    => $component->getInstance()->getId(),
                'componentTitle' => $title,
                'embedUrl'       => $embedUrl,
                'launchUrl'      => $launchUrl,
                'height'         => $component->getConfig(LtiComponent::COMPONENT_CONFIG_HEIGHT),
                'version'        => $this->getConfigLoader()->get('site_version'),
            ],
        ]);
    }

    /**
     * Create LTI launch request form
     *
     * @param Provider                     $provider
     * @param Component_ControllerAbstract $component
     * @param Folder                       $folder
     *
     * @throws AuraRouteNotFound
     */
    public function getLaunch(Provider $provider, Component_ControllerAbstract $component, Folder $folder)
    {

        /* Provider might have been deleted, despite existing here
         * despite being specified - this means it's been deleted one way or another
         * so return a generic error template instead
         */
        if ($provider->isDeleted()) {
            $this->getResponse()->content->set([
                'template' => '@ltiConsumer/unavailable.twig',
                'format'   => 'nochrome',
                'data'     => [
                    'provider'  => $provider,
                ],
            ]);

            return;
        }

        $this->user = $this->getUser();

        switch ($provider->getLaunchType()) {
            case Provider::LAUNCH_TYPE_PICKER:
                $ltiRequestType = self::LTI_LAUNCH_TYPE_PICKER;
                break;

            default:
                $ltiRequestType = self::LTI_LAUNCH_TYPE_BASIC;
                break;
        }

        // Disable history tracking so the LTI iframe doesn't cause unexpected page navigation in the main
        // Schoolbox window

        $this->setAllowBackToTheFuture(false);

        // Define LTI return URL

        $extContentReturnUrl = $this->baseUrl .
            $this->getRouteIfAuthorized('ltiConsumer.getComponentCallback', [
                'componentId' => $component->getInstance()->getId(),
            ]);

        // Launch presentation return URL is the same as the standard return URL
        // (except for Education Perfect, which is different for some reason)
        $launchPresentationReturnUrl = $extContentReturnUrl;

        // Check if the LTI component is a picker and already has a resource URL specified

        $embedUrl = trim($component->getConfig(LtiComponent::COMPONENT_CONFIG_LTI_EMBED_URL));

        // ---------------------------------------------------------------------------------------------------
        // Create array to hold launch data
        // ---------------------------------------------------------------------------------------------------

        $launch_data = [];

        // ---------------------------------------------------------------------------------------------------
        // Education perfect URL mangling
        // ---------------------------------------------------------------------------------------------------

        if (strlen($embedUrl) > 0) {
            if (stristr($embedUrl, 'educationperfect') !== false) {
                // EducationPerfect is a special snowflake:
                // it expects the homepage as the return URL?
                $launchPresentationReturnUrl = "{$this->baseUrl}/homepage/{$folder->getId()}";
                if ($ltiRequestType == self::LTI_LAUNCH_TYPE_PICKER) {
                    $ltiRequestType = self::LTI_LAUNCH_TYPE_BASIC;

                    // Put the resource url into the launch data

                    $launch_data['contentUrl'] = $embedUrl;
                }
            } elseif ($ltiRequestType === self::LTI_LAUNCH_TYPE_BASIC) {
                // Is this content which has actually been picked?
                // Maintain current behaviour (for fear of breaking any existing integrations)
                $this->response->redirect->to($embedUrl);
                return;
            } else {
                // This component is a picker, and content has been selected:
                // now launch it.
                $ltiRequestType = self::LTI_LAUNCH_TYPE_BASIC;
            }
        } else {
            $embedUrl = null;
        }

        // Define allowed return types from LTI providers

        $returnTypes = implode(',', [
            self::LTI_RETURN_TYPE_IFRAME,
            self::LTI_RETURN_TYPE_URL,
            self::LTI_RETURN_TYPE_FILE,
            self::LTI_RETURN_TYPE_IMAGE,
            self::LTI_RETURN_TYPE_LAUNCH,
        ]);

        $now = new DateTime();

        $launch_data = array_merge($launch_data, [
            'resource_link_id'                       => $folder->getId() . '_' . $component->getInstance()->getId(),
            'resource_link_title'                    => $component->getConfig(LtiComponent::COMPONENT_CONFIG_TITLE),
            'resource_link_description'              => '',
            'user_id'                                => $this->user->getId(),
            'user_image'                             => $this->getUserPortraitUrl($this->user),
            'roles'                                  => $this->getLTIRole($this->user),
            'lis_person_name_given'                  => $this->user->getFirstname(),
            'lis_person_name_family'                 => $this->user->getLastname(),
            'lis_person_name_full'                   => $this->user->getFullName(),
            'lis_person_contact_email_primary'       => $this->user->getEmail(),
            'context_id'                             => $folder->getId(),
            'context_type'                           => 'CourseSection',
            'context_title'                          => $folder->getName(),
            'context_label'                          => $folder->getCodeString(),
            'launch_presentation_locale'             => 'en-US',
            'launch_presentation_document_target'    => $provider->getTarget(),
            'launch_presentation_return_url'         => $launchPresentationReturnUrl,
            'ext_content_return_types'               => $returnTypes,
            'ext_content_return_url'                 => $extContentReturnUrl,
            'tool_consumer_instance_guid'            => $this->getConfigLoader()->get('site_school_code'),
            'tool_consumer_instance_name'            => $this->getConfigLoader()->get('school_name'),
            'tool_consumer_instance_description'     => 'Schoolbox LMS',
            'tool_consumer_instance_url'             => $this->baseUrl,
            'tool_consumer_instance_contact_email'   => $this->getConfigLoader()->get(ConfigMapper::NAME_MAIL_FROM_ADDRESS),
            'tool_consumer_info_product_family_code' => 'schoolbox',
            'tool_consumer_info_version'             => $this->getConfigLoader()->get('site_version'),
            'ext_lms'                                => 'schoolbox-' . $this->getConfigLoader()->get('site_version'),
            'lti_version'                            => self::LTI_VERSION_1,
            'lti_message_type'                       => $ltiRequestType,
            'oauth_callback'                         => 'about:blank',
            'oauth_consumer_key'                     => $provider->getKey(),
            'oauth_version'                          => '1.0',
            'oauth_nonce'                            => uniqid('', true),
            'oauth_timestamp'                        => $now->getTimestamp(),
            'oauth_signature_method'                 => 'HMAC-SHA1',
            'custom_canvas_user_id'                  => $this->user->getExternalId(),
            'custom_canvas_user_login_id'            => $this->user->getUsername(),
            ]);

        // Add picker specific option

        if ($ltiRequestType === self::LTI_LAUNCH_TYPE_PICKER) {
            $launch_data = array_merge($launch_data, [
                'request_type'                         => 'content',
                'accept_presentation_document_targets' => 'embed,frame,iframe,window',
                'accept_media_types'                   => '*/*',
                'accept_unsigned'                      => 'true',
                'accept_multiple'                      => 'false',
                'content_item_return_url'              => $this->baseUrl .
                    $this->getRouteIfAuthorized('ltiConsumer.postComponentPicker', [
                        'componentId' => $component->getInstance()->getId(),
                        'providerId'  => $provider->getId(),
                    ]),
            ]);
        }

        // ---------------------------------------------------------------------------------------------------
        // Sign the request
        // ---------------------------------------------------------------------------------------------------

        // OAuth requires that all passed keys be sorted alphabetically

        $launch_data_keys = array_keys($launch_data);
        sort($launch_data_keys);
        $launch_params = [];

        foreach ($launch_data_keys as $key) {
            array_push($launch_params, $key . "=" . rawurlencode($launch_data[$key]));
        }

        $base_string = "POST&" . urlencode($embedUrl ?? $provider->getUrl()) . "&" . rawurlencode(implode("&", $launch_params));
        $secret      = urlencode($provider->getSecret()) . "&";
        $signature   = base64_encode(hash_hmac("sha1", $base_string, $secret, true));

        // Render form that will automatically submit LTI request

        $this->getResponse()->content->set([
            'template' => '@ltiConsumer/form.twig',
            'format'   => 'nochrome',
            'data'     => [
                'params'    => $launch_data,
                'provider'  => $provider,
                'signature' => $signature,
                'embedUrl'  => $embedUrl,
            ],
        ]);
    }

    /**
     * Handle callback from an LTI provider
     *
     * @param Component_ControllerAbstract $component
     *
     * @throws MapperException
     */
    public function getComponentCallback($component)
    {
        $get = $this->getRequest()->query;

        $return_type = $get['return_type'];

        // Ultimately all return types will be treated in the same way by our LTI component
        // and it will just redirect to the target URL. The only real difference at this point
        // is that the return parameter names differ slightly.

        $height = null;
        switch ($return_type) {
            case self::LTI_RETURN_TYPE_IFRAME:
                $embedUrl  = $get['url'];
                $launchUrl = $embedUrl;
                $title     = $get['title'];
                $height    = $get['height'];
                break;

            case self::LTI_RETURN_TYPE_URL:
            case self::LTI_RETURN_TYPE_FILE:
                $embedUrl  = $get['url'];
                $launchUrl = $embedUrl;
                $title     = $get['text'];
                break;

            case self::LTI_RETURN_TYPE_IMAGE:
                $embedUrl  = $get['url'];
                $launchUrl = $embedUrl;
                $title     = $get['text'];
                $height    = $get['height'];
                break;

            case self::LTI_RETURN_TYPE_LAUNCH:
                // For launches:
                // the launch URL generates a POST request to the embed URL
                $embedUrl  = $get['url'];
                $launchUrl = $this->router->generate('ltiConsumer.getLaunch', [
                    'providerId'   => $component->getConfig(LtiComponent::COMPONENT_CONFIG_LTI_PROVIDER),
                    'componentId'  => $component->getInstance()->getId(),
                    'folderId'     => $component->getContext()->getId(),
                ]);
                $title     = $get['text'];
                break;

            // Throw an exception error if we receive an unknown
            default:
                throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
        }

        // Update the component configuration with any details we are able to retrieve

        if (!empty($title)) {
            $this->updateComponentValue($component, LtiComponent::COMPONENT_CONFIG_TITLE, $title);
        }

        if (!empty($embedUrl)) {
            $this->updateComponentValue($component, LtiComponent::COMPONENT_CONFIG_LTI_EMBED_URL, $embedUrl);
        }

        if (!empty($height)) {
            $this->updateComponentValue($component, LtiComponent::COMPONENT_CONFIG_HEIGHT, $height);
        }

        // Rather than just redirecting the iframe directly to the requested resource we need to do a bit
        // of javascript shenanigans.
        //
        // Because this code is operating inside the LTI iframe, the component on the homepage will not have
        // has its title or iframe height adjusted to the new parameters until the page is reloaded. The
        // javascript inside this template updates these in the DOM.

        $this->getResponse()->content->set([
            'template' => '@ltiConsumer/redirect.twig',
            'format'   => 'nochrome',
            'data'     => [
                'componentId'    => $component->getInstance()->getId(),
                'componentTitle' => $title,
                'embedUrl'       => $embedUrl,
                'launchUrl'      => $launchUrl,
                'height'         => $height,
                'version'        => $this->getConfigLoader()->get('site_version'),
            ],
        ]);

        return;
    }

    /**
     * Return the URL to the users portrait
     *
     * @param User   $user
     * @param string $size
     *
     * @return string
     */
    private function getUserPortraitUrl($user, $size = 'square80')
    {
        return "{$this->baseUrl}/portrait.php?id={$user->getId()}&size={$size}";
    }

    /**
     * Return the LTI role for a schoolbox user
     *
     * @param User $user
     *
     * @return string One of the allowed LTI role types (as defined by LTI_ROLE_* constants)
     */
    public function getLTIRole($user)
    {
        if ($user->getRole()->isStudent()) {
            return self::LTI_ROLE_STUDENT;
        }

        if ($user->getRole()->isStaff()) {
            return self::LTI_ROLE_INSTRUCTOR;
        }

        return self::LTI_ROLE_GUEST;
    }

    /**
     * Update a components configuration variable
     *
     * @param Component_ControllerAbstract $component
     * @param string                       $key
     * @param mixed                        $value
     *
     * @throws MapperException
     */
    private function updateComponentValue($component, $key, $value)
    {
        // Locate the configuration definition

        try {
            $configDefinition = $this->componentConfigDefinitionMapper->findOneByColumnList([
                'key'                     => $key,
                'component_definition_id' => $component->getInstance()->getDefinitionId(),
            ]);
        } catch (MapperException $e) {
            // If the component configuration definition does not exist then something is wrong with the database
            // so we cannot proceed

            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
        }

        // Try to find an existing configuration instance for the requested configuration item

        $configInstance = $this->componentConfigMapper->findByColumnList([
            'component_config_definition_id' => $configDefinition->getId(),
            'component_instance_id'          => $component->getInstance()->getId(),
        ])->first();

        // If the configuration instance didn't already exist in the database we need to create a new one

        if (empty($configInstance)) {
            $configInstance = new Component_Config();
            $configInstance->setConfigDefinitionId($configDefinition->getId());
            $configInstance->setInstanceId($component->getInstance()->getId());
        }

        // Update the value of the configuration in the database

        $configInstance->setValue($value);
        $this->componentConfigMapper->save($configInstance);
    }
}

/*
 * This code (along with the entire LTI specification) will self-destruct in 5 seconds...
 *
          ,--.!,
       __/   -*-
     ,d08b.  '|`
     0088MM
     `9MMP'
*/
