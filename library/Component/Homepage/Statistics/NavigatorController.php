<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_Statistics_NavigatorController extends Component_Homepage_Statistics_ControllerAbstract
{

    private $rating;
    private $hits;

    const STAR_COUNT = 5;

    public function execute()
    {
    }

    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'navigator')) {
            return false;
        }

        // parse time-period navigator
        // 100 days
        $this->setBlock($templateHandle, 'date_start_option', 'date_start_option_block');
        $this->setBlock($templateHandle, 'date_end_option', 'date_end_option_block');

        $dt = new DateTime('midnight');

        // for one reason or another, an extra day needs to be added here
        $startDT = DateTime::createFromFormat('U', $this->dateRange[0] + (3600 * 24));
        $endDT = DateTime::createFromFormat('U', $this->dateRange[1]);

        for ($i = 0; $i < 12; ++$i) {
            $this->setVar(array(
                'DATE_START_SELECTED' => $dt->format('Ym') == $startDT->format('Ym') ? ' selected="selected"' : '',
                'DATE_START_VALUE'    => $dt->format('Y-m'),
                'DATE_START_NAME'     => $dt->format('M, Y')
            ));

            $this->setVar(array(
                'DATE_END_SELECTED' => $dt->format('Ym') == $endDT->format('Ym') ? ' selected="selected"' : '',
                'DATE_END_VALUE'    => $dt->format('Y-m'),
                'DATE_END_NAME'     => $dt->format('M, Y')
            ));

            $this->parse('date_start_option_block', 'date_start_option', true);
            $this->parse('date_end_option_block', 'date_end_option', true);

            $dt->add(DateInterval::createFromDateString('-1 month'));
        }


        // parse dropdown
        $this->setBlock($templateHandle, 'drop_option', 'drop_option_block');

        $result = Database::getInstance()->query("SELECT DISTINCT group_type t FROM groups");

        $groupTypes = $result->fetchAll(PDO::FETCH_ASSOC);
        array_unshift($groupTypes, array('t' => 'all'));

        foreach ($groupTypes as $group) {
            $this->setVar(array(
                'DROP_SELECTED' => $this->groupType == $group['t'] ? 'selected="selected"' : '',
                'DROP_VALUE'    => $group['t'],
                'DROP_NAME'     => ucfirst($group['t'])
            ));

            $this->parse('drop_option_block', 'drop_option', true);
        }

        return true;
    }
}
