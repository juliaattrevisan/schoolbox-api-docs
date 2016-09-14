<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Schoolbox\Resource\Folder\Model as Folder;

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

    /**
     * @param Folder     $context
     * @param array|null $config
     */
    public function setup(Folder $context, array $config = array())
    {
        parent::setup($context, $config);

        $accessor = \Schoolbox\Permission\Access\Access::getInstance();

        /* ffs arrays
         * TODO: This should just be an object, never an array
         */
        if (is_object($context)) {
            $contextId = $context->getId();
            $this->setContentPermission($accessor->getFolderAccess($this->getUser(), [$context->getId()]));
        } elseif (is_array($context)) {
            $contextId = $context['id'];
            $this->setContentPermission($accessor->getFolderAccess($this->getUser(), [$context['id']]));
        }

        $args = $this->getSitePage()->arg;

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

        $this->getSitePage()->setVar(array(
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

    /**
     * Convenience method for PHPLib-based Components to render the 'AltSourceDenied' empty state
     * @return bool
     */
    protected function renderAltSourceDenied(){
        throw new DomainException('HomepageStatistic components do not currently allow alternative data sources');
    }

    /**
     * For now, HomepageStatistic Components are unable to use Alt Data Sources
     *
     * @throws DomainException
     */
    protected function renderAltSourceDeleted(){
        throw new DomainException('HomepageStatistic components do not currently allow alternative data sources');
    }

    /* Getters / Setters
     */

    /**
     * @return Folder
     */
    public function getContext()
    {
        return parent::getContext();
    }


    /**
     * @param Folder $context
     * @return static
     */
    protected function setContext(Folder $context)
    {
        parent::setContext($context);
        return $this;
    }

    /**
     * @return Folder
     */
    public function getSource()
    {
        return parent::getSource();
    }

    /**
     * @param Folder $context
     * @return static
     */
    public function setSource(Folder $context)
    {
        parent::setSource($context);
        return $this;
    }
}
