<?php

/**
 * Calendar_Api_Config_Connector_Vcal
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Calendar_Api_Config_Connector_Vcal extends Calendar_Api_Config_Connector_Abstract
{
    /**
     * Hardcoded to match db
     *
     * @var string
     */
    protected string $connectorClass = 'Vcal';

    /**
     * {@inheritdoc}
     */
    public function getDefaultLabel(): string
    {
        return 'iCalendar Events';
    }

    // DANGER! Private variables of this class must not be renamed
    // -----------------------------------------------------------
    // Objects of this class are serialized and then shared with outside parties.  Private variable names thus become
    // publicly exposed field names that outside parties might rely on and so should not change.

    /**
     * A url to a vcal stream
     *
     * @var string
     */
    private $url = ''; // This variable must not be renamed.  See reason in comment above.

    /**
     * The authorization token to send to the vcal stream
     *
     * @var string
     */
    private $authorization = ''; // This variable must not be renamed.  See reason in comment above.

    /**
     * Campus visibility for this stream
     *
     * @var array
     */
    private $campus = array(); // This variable must not be renamed.  See reason in comment above.

    /**
     * Group visibility for this stream
     *
     * @var array
     */
    private $groups = array(); // This variable must not be renamed.  See reason in comment above.

    /**
     * Render configs into admin screen
     *
     * @param SitePage $page
     *
     * @return mixed|void
     */
    public function render($page)
    {
        $page->setVar(
            array(
                'CONNECTOR_ID'    => $this->getId(),
                'CONNECTOR_LABEL' => Scrub::text($this->getLabel()),
                'CONNECTOR_CLASS' => Scrub::text($this->getConnectorClass()),
                'URL'             => $this->getUrl(),
                'AUTHORIZATION'   => $this->getAuthorization(),
            )
        );

        $page->parse('has_multiple_options_block', 'has_multiple_options');

        /* Render event type options
         */
        $this->renderEventType($page, $this->getLuEventType());

        /* Permissions for this connector
         */
        $userHandler = new User();

        /* Campus permissions
         */
        $page->checkList(
            'campus_list',
            $userHandler->getCampusList(),
            $this->getCampusIds(),
            'configFragment',
            [
                'id' => 'CAMPUS_ID',
                'name' => 'CAMPUS_NAME'
            ]
        );

        /* Group permissions
         */
        $page->checkList(
            'group_list',
            $userHandler->getGroupList(),
            $this->getGroupIds(),
            TEMPLATE_content,
            [
                'id' => 'GROUP_ID',
                'name' => 'GROUP_NAME'
            ]
        );
    }

    /**
     * Check if current user has permissions to see this event
     *
     * @param array $userCampusIds
     * @param int $userGroupId
     *
     * @return bool
     */
    public function hasPermissions($userCampusIds, $userGroupId)
    {
        if ((count(array_intersect($userCampusIds, $this->getCampusIds()))) && in_array($userGroupId, $this->getGroupIds())) {
            return true;
        }

        return false;
    }

    /* Getters/Setters
     */

    /**
     * Set url
     *
     * @param string $url
     */
    public function setUrl($url)
    {
        $this->url = $url;
    }

    /**
     * Get url
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Set authorization
     *
     * @param string $authorization
     */
    public function setAuthorization(string $authorization): void
    {
        $this->authorization = $authorization;
    }


    /**
     * @return string
     */
    public function getAuthorization(): string
    {
        return $this->authorization;
    }

    /**
     * Set campusIds
     *
     * @param array $campusIds
     */
    public function setCampusIds(array $campusIds): void
    {
        $this->campus = $campusIds;
    }

    /**
     * Get campusIds
     *
     * @return array
     */
    public function getCampusIds(): array
    {
        return $this->campus;
    }

    /**
     * Set groupIds
     *
     * @param array $groupIds
     */
    public function setGroupIds(array $groupIds): void
    {
        $this->groups = $groupIds;
    }

    /**
     * Get groupIds
     *
     * @return array
     */
    public function getGroupIds(): array
    {
        return $this->groups;
    }


    /**
     * This function replaces tokens in the URL with user parameters, to allow personalisation of VCAL URL
     * @param \Schoolbox\User $user
     */
    public function tokenReplaceUrl(\Schoolbox\User $user): void
    {
        $urlTokens = [
            '[id]',
            '[externalId]',
            '[username]' ,
            '[email]' ,
        ];

        $urlReplacement = [
            $user->getId(),
            $user->getExternalId(),
            $user->getUsername(),
            $user->getEmail()
        ];

        $this->url = str_ireplace($urlTokens, $urlReplacement, $this->url);
    }
}
