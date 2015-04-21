<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
abstract class Component_Homepage_Statistics_ControllerAbstract extends Component_ControllerAbstract
{

    protected $dateRange = array();
    protected $groupType;
    private $validGroups = array('student','parent','staff','guest');

    public function setup($context = null, array $config = array())
    {
        parent::setup($context, $config);
        $g = new Group();

        /* ffs arrays
         * TODO: This should just be an object, never an array
         */
        if (is_object($context)) {
            $contextId = $context->getId();
            $this->permission = $g->getPermission($context->getId());
        } elseif (is_array($context)) {
            $contextId = $context['id'];
            $this->permission = $g->getPermission($context['id']);
        }

        $args = $this->sitepage->arg;

        if (isset($args[1])) {
            $start = explode('-', $args[1]);

            // from 1 to 2
            if (isset($args[2])) {
                $end = explode('-', $args[2]);
            } // just 1
            else {
                $end = explode('-', $args[1]);
            }

            ++$end[1];
            if ($end[1] > 12) {
                ++$end[0];
                $end[1] -= 12;
            }

            $this->dateRange = array(
                strtotime(implode('-', $start).'-01'),
                strtotime(implode('-', $end).'-01') - (24 * 3600)
            );

            $args[3] = strtolower($args[3]);
            $this->groupType = isset($args[3]) && in_array($args[3], $this->validGroups) ? $args[3] : 'all';

        } // show default (all time?)
        else {
            $this->groupType = 'all';
        }

        $this->sitepage->setVar(array(
            'ID'    => $contextId,
            'GROUP' => $this->groupType
        ));
    }

    /**
     * @param string $templateName
     * @param string $templatePath
     * @return string
     */
    protected function getTemplate ($templateName = '', $templatePath = '')
    {

        if (empty($templatePath)) {
            $templatePath = "component/homepage/statistics";
        }

        return parent::getTemplate($templateName, $templatePath);
    }
}
