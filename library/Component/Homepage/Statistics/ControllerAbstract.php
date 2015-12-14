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

        // $args[1]: start month
        $start = Alaress_Date_Format::now();
        if (isset($args[1])) {
            try {
                $start = new Alaress_Date_Format($args[1], 'Y-m');
            } catch (InvalidArgumentException $e) {
                // noop
            }
        }
        $start = $start->startOfMonth();

        // $args[2]: end month
        $end = clone($start);
        $end = $end->endOfMonth();
        if (isset($args[2])) {
            try {
                $end = new Alaress_Date_Format($args[2], 'Y-m');
            } catch (InvalidArgumentException $e) {
                // noop
            }
        }

        $this->dateRange = [$start, $end];

        // args[3]: group type, default all
        $this->groupType = isset($args[3]) && in_array($args[3], $this->validGroups) ? $args[3] : 'all';

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
