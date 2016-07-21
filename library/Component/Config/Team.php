<?php
/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Schoolbox\SportsFixtures\Team\Model as TeamModel;
use Schoolbox\SportsFixtures\Team\Mapper as TeamMapper;

class Component_Config_Team extends Component_Config_Abstract
{
    /**
     * Parse
     *
     * @param SitePage $page
     * @param string $key
     * @param string $keyblock
     * @param mixed $configValue
     * @param Component_ControllerAbstract $component
     */
    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {
        $teamMapper = new TeamMapper();

        try {
            $team = $teamMapper->findById($configValue);
        } catch (\Exception $e) {
            $team = new TeamModel();
        }

        /* Because you can have multiple textbox components displaying the
         * same textbox, just pick the first one that uses the textbox
         */

        $page->setVar(
            array(
                'COMPONENT_TEAM_ID'      => $team->getId(),
                'COMPONENT_TEAM_NAME'    => $team->getName()
            )
        );
    }
}
