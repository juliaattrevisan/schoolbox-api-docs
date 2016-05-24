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
    /**
     * @var Alaress_Date_Format
     */
    protected $startDate;

    /**
     * @var Alaress_Date_Format
     */
    protected $endDate;

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
            $this->contentPermission = $g->getPermission($context->getId());
        } elseif (is_array($context)) {
            $contextId = $context['id'];
            $this->contentPermission = $g->getPermission($context['id']);
        }

        $args = $this->sitepage->arg;

        // $args[1]: start month
        $this->startDate = Alaress_Date_Format::now();
        if (isset($args[1])) {
            try {
                $this->startDate = new Alaress_Date_Format($args[1], 'Y-m');
            } catch (InvalidArgumentException $e) {
                // noop
            }
        }
        $this->startDate = $this->startDate->startOfMonth();

        // $args[2]: end month
        $this->endDate = clone($this->startDate);
        if (isset($args[2])) {
            try {
                $this->endDate = new Alaress_Date_Format($args[2], 'Y-m');
            } catch (InvalidArgumentException $e) {
                // noop
            }
        }
        $this->endDate = $this->endDate->endOfMonth();

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
