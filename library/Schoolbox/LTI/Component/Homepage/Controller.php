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
use IMSGlobal\LTI\ToolProvider\Service as LTIService;

class Controller extends Component_Homepage_ControllerAbstract
{
    /**
     * Injectable constructor
     *
     * @param Component_Instance $instance
     * @param User               $user
     * @param SitePage           $sitepage
     */
    public function __construct(Component_Instance $instance, User $user, SitePage $sitepage) {
        parent::__construct($instance, $user, $sitepage);
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
        $consumer = new LTI\ToolConsumer('samples.valence.desire2learn.com');
        $consumer->name = '12345';
        $consumer->secret = 'secret';
        $consumer->enabled = true;
        $consumer->save();

        $consumer->doServiceRequest($service,
            'POST',
            'application/vnd.ims.lis.v2p1.result+json',
            [
            'resource_link_id' => '120988f929-274612',
            'resource_link_title' => 'Weekly Blog',
            'resource_link_description' => 'A Weekly Blog',
            'user_id' => '292832126',
            'roles' => 'Instructor',
            'lis_person_name_full' => 'Jane Q. Public',
            'lis_person_name_family' => 'Public',
            'lis_person_name_given' => 'Given',
            'lis_person_contact_email_primary' => 'user@school.edu',
            'lis_person_sourcedid' => 'school.edu:user',
            'context_id' => '456434513',
            'context_title' => 'Design of Personal Environments',
            'context_label' => 'SI182',
            'tool_consumer_info_product_family_code' => 'ims',
            'tool_consumer_info_version' => '1.1',
            'tool_consumer_instance_guid' => 'lmsng.school.edu',
            'tool_consumer_instance_description' => 'University of School (LMSng)',
        ]);

        var_dump($response);

        if ($response->ok) {
        }

        exit;

        // Render this component out

        return $this->renderTwigTemplate(
            $templateHandle,
            '@lti/component.twig',
            array(
                'session'               => $_SESSION,
                'componentInstanceId'   => $this->getInstance()->getId(),
                'componentClass'        => $this->getComponentName()
            ),
            $this->hasWriteAccess() || !$this->getConfig('compactMode')
        );
    }
}
