<?php
/**
 * LTI v1.2 consumer implementation
 *
 * Implementation guide can be found at: https://www.imsglobal.org/specs/ltiv1p2/implementation-guide
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\LTI\Consumer;

use Alaress\Web\Controller\Base;
use Component_Config;
use Component_ConfigDefinitionMapper;
use Component_ConfigMapper;
use Component_ControllerAbstract;
use DateTime;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\LTI\Provider\Model as Provider;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\User;

class Controller extends Base
{
    /** @var Component_ConfigDefinitionMapper $componentConfigDefinitionMapper */
    private $componentConfigDefinitionMapper;

    /** @var Component_ConfigMapper $componentConfigMapper */
    private $componentConfigMapper;

    /**
     * Allowed LTI return types
     *
     * This is not part of the core LTI specification. Further documentation can be found here:
     * https://www.edu-apps.org/extensions/content.html
     *
     * OEmbed has been intentionally skipped for now as its a pain in the butt to implement and I
     * don't currently have any examples of LTI providers that use this
     */
    const RETURN_TYPE_IFRAME = 'iframe';
    const RETURN_TYPE_URL = 'url';
    const RETURN_TYPE_FILE = 'file';
    const RETURN_TYPE_IMAGE = 'image_url';
    const RETURN_TYPE_LAUNCH = 'lti_launch_url';

    /**
     * Allowed LTI user roles
     */
    const LTI_ROLE_STUDENT = 'Student';
    const LTI_ROLE_INSTRUCTOR = 'Instructor';
    const LTI_ROLE_GUEST = 'Guest';

    /**
     * Controller constructor.
     */
    public function __construct()
    {
        $this->componentConfigMapper           = Component_ConfigMapper::getInstance();
        $this->componentConfigDefinitionMapper = Component_ConfigDefinitionMapper::getInstance();
    }

    /**
     * Update component configuration and set the provider it will use
     *
     * @param Provider $provider
     * @param Component_ControllerAbstract $component
     */
    public function postComponentProvider($provider, $component)
    {
        $post = $this->getRequest()->post;

        $this->updateComponentValue($component, 'ltiprovider', $provider->getId());
        $this->updateComponentValue($component, 'componentname', $provider->getName());

        if (isset($post['url'])) {
            $this->updateComponentValue($component, 'description', $post['url']);
        }

        $this->getResponse()->content->set([
            'format' => 'json',
            'data' => [
                'result' => 'OK',
                'providerName' => $provider->getName(),
                'url' => $post['url']
            ]
        ]);
    }

    /**
     * Create LTI launch form
     *
     * @param Provider $provider
     * @param Component_ControllerAbstract $component
     * @param Folder $folder
     */
    public function getLaunch($provider, $component, $folder)
    {
        /** @var ConfigMapper $configMapper */
        $configMapper = $this->getDi()->get('config');
        
        // Retrieve current logged in user

        $user = $this->getUser();

        // Disable history tracking so the LTI iframe doesn't cause unexpected page navigation

        $this->setAllowBackToTheFuture(false);

        // Check if the LTI component already has a resource URL specified, if it does we will bypass LTI and
        // just redirect the components iframe to tha address

        $url = $component->getConfig('url');

        if (!empty($url)) {
            $this->getResponse()->redirect->to($url);
        }

        // Otherwise go to the LTI provider

        $baseUrl = "{$_SERVER['REQUEST_SCHEME']}://{$_SERVER['SERVER_NAME']}";

        // Define return URL

        $extContentReturnUrl = $baseUrl . $this->getRouteIfAuthorized('ltiConsumer.getComponentCallback', [
                'componentId' => $component->getInstance()->getId()
            ]);

        // Define allowed return types from LTI providers

        $returnTypes = implode(',', [
            self::RETURN_TYPE_IFRAME,
            self::RETURN_TYPE_URL,
            self::RETURN_TYPE_FILE,
            self::RETURN_TYPE_IMAGE,
            self::RETURN_TYPE_LAUNCH
        ]);

        $launch_data = [
            'resource_link_id' => $folder->getId() . '_' . $component->getInstance()->getId(),
            'resource_link_title' => $component->getConfig('componentname'),
            'resource_link_description' => '',
            'user_id' => $user->getId(),
            'user_image' => $user->getPortraitUrl(),
            'roles' => $this->getLTIRole($user),
            'lis_person_name_given' => $user->getFirstname(),
            'lis_person_name_family' => $user->getLastname(),
            'lis_person_name_full' => $user->getFullName(),
            'lis_person_contact_email_primary' => $user->getEmail(),
            'context_id' => $folder->getId(),
            'context_type' => 'CourseSection',
            'context_title' => $folder->getName(),
            'context_label' => $folder->getCodeString(),
            'launch_presentation_locale' => 'en-US',
            'launch_presentation_document_target' => 'iframe',
            'launch_presentation_return_url' => "{$baseUrl}homepage/{$folder->getId()}",
            'ext_content_return_types' => $returnTypes,
            'ext_content_return_url' => $extContentReturnUrl,
            'tool_consumer_instance_guid' => $configMapper->get('site_location'),
            'tool_consumer_instance_name' => $configMapper->get('school_name'),
            'tool_consumer_instance_description' => 'Schoolbox LMS',
            'tool_consumer_instance_url' => $baseUrl,
            'tool_consumer_instance_contact_email' => $configMapper->get('mail_from_address'),
            'tool_consumer_info_product_family_code' => 'schoolbox',
            'tool_consumer_info_version' => $configMapper->get('site_version'),
            'ext_lms' => 'schoolbox-' . $configMapper->get('site_version')
        ];

        // Learning Field custom URL mangling

        if (strtolower($provider->getUrl()) == 'https://bc.vitalsource.com/books/book') {
            $custom = $component->getConfig('description');
            preg_match('/\/books\/([A-Z0-9]+)\//i', $custom, $matches);
            $launch_data['custom_vbid'] = $matches[1];
            preg_match('/\/pageid\/(.*)/i', $custom, $matches);
            $launch_data['custom_book_location'] = 'pageid/' . $matches[1];
        }

        // Sign LTI request using OAuth

        $now = new DateTime();

        $launch_data["lti_version"]            = "LTI-1p0";
        $launch_data["lti_message_type"]       = "basic-lti-launch-request";
        $launch_data["oauth_callback"]         = "about:blank";
        $launch_data["oauth_consumer_key"]     = $provider->getKey();
        $launch_data["oauth_version"]          = "1.0";
        $launch_data["oauth_nonce"]            = uniqid('', true);
        $launch_data["oauth_timestamp"]        = $now->getTimestamp();
        $launch_data["oauth_signature_method"] = "HMAC-SHA1";

        // OAuth requires that all passed keys be sorted alphabetically

        $launch_data_keys = array_keys($launch_data);
        sort($launch_data_keys);
        $launch_params = [];

        foreach ($launch_data_keys as $key) {
            array_push($launch_params, $key . "=" . rawurlencode($launch_data[$key]));
        }

        $base_string = "POST&" . urlencode($provider->getUrl()) . "&" . rawurlencode(implode("&", $launch_params));
        $secret      = urlencode($provider->getSecret()) . "&";
        $signature   = base64_encode(hash_hmac("sha1", $base_string, $secret, true));

        // Render form that will automatically submit LTI request

        $this->getResponse()->content->set([
            'template' => '@ltiConsumer/form.twig',
            'format' => 'nochrome',
            'data' => [
                'params' => $launch_data,
                'provider' => $provider,
                'signature' => $signature
            ]
        ]);
    }

    /**
     * Handle callback from an LTI provider
     *
     * @param Component_ControllerAbstract $component
     */
    public function getComponentCallback($component)
    {
        /** @var ConfigMapper $configMapper */
        $configMapper = $this->getDi()->get('config');

        $get = $this->getRequest()->query;

        $return_type = $get['return_type'];

        // Ultimately all return types will be treated in the same way by our LTI component
        // and it will just redirect to the target URL. The only real difference at this point
        // is that the return parameter names differ slightly.

        $url = null;
        $height = null;
        $title  = null;

        switch ($return_type) {
            case self::RETURN_TYPE_IFRAME:
                $url    = $get['url'];
                $title  = $get['title'];
                $height = $get['height'];
                break;

            case self::RETURN_TYPE_URL:
                $url   = $get['url'];
                $title = $get['text'];
                break;

            case self::RETURN_TYPE_FILE:
                $url   = $get['url'];
                $title = $get['text'];
                break;

            case self::RETURN_TYPE_IMAGE:
                $url    = $get['url'];
                $title  = $get['text'];
                $height = $get['height'];
                break;

            case self::RETURN_TYPE_LAUNCH:
                $url   = $get['url'];
                $title = $get['text'];
                break;

            // Throw an exception error if we receive an unknown

            default:
                throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
        }

        // Update the component configuration with any details we are able to retrieve

        if (!empty($title)) {
            $this->updateComponentValue($component, 'componentname', $title);
        }

        if (!empty($url)) {
            $this->updateComponentValue($component, 'url', $url);
        }

        if (!empty($height)) {
            $this->updateComponentValue($component, 'pixel', $height);
        }

        // Rather than just redirecting the iframe directly to the requested resource we need to do a bit
        // of javascript shenanigans.
        //
        // Because this code is operating inside the LTI iframe, the component on the homepage will not have
        // has its title or iframe height adjusted to the new parameters until the page is reloaded. The
        // javascript inside this template updates these in the DOM.

        $this->getResponse()->content->set([
            'template' => '@ltiConsumer/redirect.twig',
            'format' => 'nochrome',
            'data' => [
                'componentId' => $component->getInstance()->getId(),
                'componentTitle' => $title,
                'url' => $url,
                'height' => $height,
                'version' => $configMapper->get('site_version')
            ]
        ]);

        return;
    }

    /**
     * Return the LTI role for a schoolbox user
     *
     * @param User $user
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
     * @param string $key
     * @param mixed $value
     */
    private function updateComponentValue($component, $key, $value)
    {
        // Locate the configuration definition

        try {
            $configDefinition = $this->componentConfigDefinitionMapper->findOneByColumnList([
                'key' => $key,
                'component_definition_id' => $component->getInstance()->getDefinitionId()
            ]);
        } catch (MapperException $e) {
            // If the component configuration definition does not exist then something is wrong with the database
            // so we cannot proceed

            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
        }

        // Try to find an existing configuration instance for the requested configuration item

        $configInstance = $this->componentConfigMapper->findByColumnList([
            'component_config_definition_id' => $configDefinition->getId(),
            'component_instance_id' => $component->getInstance()->getId()
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
