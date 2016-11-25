<?php
namespace Schoolbox\LTI\Consumer;

use Alaress\Web\Controller\Base;
use Component_ControllerAbstract;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\LTI\Provider\Model as Provider;
use Schoolbox\Resource\Folder\Model as Folder;

class Controller extends Base
{
    /**
     * Controller constructor.
     */
    public function __construct()
    {
    }

    /**
     * Create LTI launch form
     * @param Provider $provider
     * @param Component_ControllerAbstract $component
     * @param Folder $folder
     */
    public function getLaunch($provider, $component, $folder) {

        $launch_data = [
            'resource_link_id' => $component->getInstance()->getId(),
            'resource_link_title' => $component->getConfig('componentname'),
            'resource_link_description' => '',
            'user_id' => $this->getUser()->getId(),
            'user_image' => urlencode("http://{$_SERVER['SERVER_NAME']}/portrait.php?userId={$this->getUser()->getExternalId()}&size=square80"),
            'roles' =>
                $this->getUser()->getRole()->isStudent() ? 'Student' : (
                $this->getUser()->getRole()->isStaff() ? 'Instructor' :
                    'Guest'
                ),
            'lis_person_name_given' => $this->getUser()->getFirstname(),
            'lis_person_name_family' => $this->getUser()->getLastname(),
            'lis_person_name_full' => $this->getUser()->getFullName(),
            'lis_person_contact_email_primary' => $this->getUser()->getEmail(),
            'context_id' => $folder->getId(),
            'context_type' => 'CourseSection',
            'context_title' => $folder->getName(),
            'context_label' => $folder->getCodeString(),
            'launch_presentation_locale' => 'en-US',
            'launch_presentation_document_target' => 'iframe',
            'launch_presentation_return_url' => urlencode('#'),
            'tool_consumer_instance_guid' => $this->getDi()->get('config')->get('site_location'),
            'tool_consumer_instance_name' => 'school_name',
            'tool_consumer_instance_description' => 'Schoolbox LMS',
            'tool_consumer_instance_url' => urlencode("http://" . $_SERVER['SERVER_NAME']),
            'tool_consumer_instance_contact_email' => 'mail_from_address',
            'tool_consumer_info_product_family_code' => 'schoolbox',
            'tool_consumer_info_version' => 'site_version',
            'ext_lms' => 'schoolbox-' . 'site_version'
        ];

        // Learning Field custom URL mangling

        if (strtolower($provider->getUrl()) == 'https://bc.vitalsource.com/books/book') {
            $custom = $component->getConfig('description');
            preg_match('/\/books\/(.*?)\//i', $custom, $matches);
            $launch_data['custom_vbid'] = $matches[1];
            preg_match('/\/pageid\/(.*)/i', $custom, $matches);
            $launch_data['custom_book_location'] = 'pageid/' . $matches[1];
        }

        // Sign request

        $now = new \DateTime();

        $launch_data["lti_version"] = "LTI-1p0";
        $launch_data["lti_message_type"] = "basic-lti-launch-request";

        $launch_data["oauth_callback"] = "about:blank";
        $launch_data["oauth_consumer_key"] = $provider->getKey();
        $launch_data["oauth_version"] = "1.0";
        $launch_data["oauth_nonce"] = uniqid('', true);
        $launch_data["oauth_timestamp"] = $now->getTimestamp();
        $launch_data["oauth_signature_method"] = "HMAC-SHA1";

        // OAuth requires that keys be sorted alphabetically

        $launch_data_keys = array_keys($launch_data);
        sort($launch_data_keys);
        $launch_params = array();
        foreach ($launch_data_keys as $key) {
            array_push($launch_params, $key . "=" . rawurlencode($launch_data[$key]));
        }
        $base_string = "POST&" . urlencode($provider->getUrl()) . "&" . rawurlencode(implode("&", $launch_params));
        $secret = urlencode($provider->getSecret()) . "&";
        $signature = base64_encode(hash_hmac("sha1", $base_string, $secret, true));

        $this->getResponse()->content->set(
            array(
                'template' => '@ltiConsumer/form.twig',
                'format'   => 'nochrome',
                'data' => [
                    'params' => $launch_data,
                    'provider' => $provider,
                    'signature' => $signature
                ],
            )
        );
    }
}

/*
 * This code will self-destruct in 5 seconds...
 *
          ,--.!,
       __/   -*-
     ,d08b.  '|`
     0088MM
     `9MMP'
*/
