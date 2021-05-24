<?php

use Schoolbox\L10n\RosettaStone;
use Schoolbox\L10n\RosettaStoneFactory;

/**
 * Calendar_Api_Config_Connector_Schoolbox
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Calendar_Api_Config_Connector_EOTC extends Calendar_Api_Config_Connector_Abstract
{
    /**
     * Hardcoded to match db
     *
     * @var string
     */
    protected $connectorClass = 'EOTC';

    /**
     * @var RosettaStone
     */
    private $stone;

    /**
     * Calendar_Api_Config_Connector_EOTC constructor.
     */
    public function __construct()
    {
        // TODO inject RosettaStone
        $stoneFactory = new RosettaStoneFactory();
        $this->stone  = $stoneFactory->build(Schoolbox\L10n\Mapper::getInstance());
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultLabel()
    {
        return $this->stone->translate('eotc', false, true);
    }


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
            )
        );
        $page->setVar('has_multiple_options_block', '');
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
        return true;
    }
}
