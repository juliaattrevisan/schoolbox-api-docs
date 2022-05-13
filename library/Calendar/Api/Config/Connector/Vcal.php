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
        return 'VCal Events';
    }

    /**
     * A url to a vcal stream
     *
     * @var string
     */
    private $url = '';

    /**
     * The authorization token to send to the vcal stream
     *
     * @var string
     */
    private $authorization = '';

    /**
     * Campus visibility for this stream
     *
     * @var array
     */
    private $campus = array();

    /**
     * Group visibility for this stream
     *
     * @var array
     */
    private $groups = array();

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
            $this->getCampus(),
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
            $this->getGroups(),
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
     * @param array $userCampus
     * @param int $userGroup
     *
     * @return bool
     */
    public function hasPermissions($userCampus, $userGroup)
    {
        if ((count(array_intersect($userCampus, $this->getCampus()))) && in_array($userGroup, $this->getGroups())) {
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
     * Set campus
     *
     * @param array $campus
     */
    public function setCampus($campus)
    {
        $this->campus = $campus;
    }

    /**
     * Get campus
     *
     * @return array
     */
    public function getCampus()
    {
        return $this->campus;
    }

    /**
     * Set groups
     *
     * @param array $groups
     */
    public function setGroups($groups)
    {
        $this->groups = $groups;
    }

    /**
     * Get groups
     *
     * @return array
     */
    public function getGroups()
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
