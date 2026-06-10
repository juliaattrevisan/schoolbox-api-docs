<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Permission\Access\Access;

/**
 * @method null getContext()
 * @method Folder|null getSource()
 */
abstract class Component_Dashboard_ControllerAbstract extends Component_ControllerAbstract
{
    /** @var Access */
    private $access;

    /**
     * @param Component_Instance $instance
     * @param \Schoolbox\User $user
     * @param SitePage $sitepage
     */
    public function __construct(Component_Instance $instance, \Schoolbox\User $user, SitePage $sitepage)
    {
        parent::__construct($instance, $user, $sitepage);
        $this->access = \Schoolbox\Permission\Access\Access::getInstance();
    }

    /**
     * Sets up a dashboard component.
     *
     * @param null       $context
     */
    public function setup($context = null)
    {
        parent::setup($context);

        $this->setContextPermission(UserAccess::ACCESS_FULL);

        if ($this->isUsingAltSource()) {
            /**
             * on the occasion that getSource is set/valid, but getSource()->getId() is null.
             * getsource has been deleted in the database, but is still being referred to. so also set the source as 0
             * we deal with this in `library/Component/ControllerAbstract.php` when we run `isSourceRenderable`
             * */
            //if getSource->GetId is null (database deleted, but still referred to) also show as deleted.
            if ($this->getSource() && $this->getSource()->getId() != null) {
                $sourceId = $this->getSource()->getId();
            } else {
                $sourceId = 0;
            }

            try {
                $this->setContentPermission(min($this->access->getFolderAccess($this->getUser(), $sourceId), $this->getMaximumPermission()));
            } catch (\Schoolbox\Mapper\Exception $e) {
                /**
                 * If the alt source has been deleted, catch that here
                 * So that it doesn't escalate to the kernel and 404 the whole page
                 */
                $this->setContentPermission(UserAccess::ACCESS_DENY);
            }
        } else {
            $this->setContentPermission(UserAccess::ACCESS_FULL);
        }
    }

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute()
    {
        // Hide Dashboard components if the folder they pull data from is soft-deleted
        return (!$this->isUsingAltSource() || !$this->getSource()->isDeleted());
    }

    /**
     * @inheritdoc
     */
    protected function getTemplate($templateName = '', $templatePath = '')
    {

        if (empty($templatePath)) {
            $templatePath = "component/dashboard";
        }

        return parent::getTemplate($templateName, $templatePath);
    }

    /* Getters / Setters
     */

    /**
     * @param null $context
     * @return static
     */
    protected function setContext($context = null)
    {
        // Dashboard components never have context
        parent::setContext(null);
        return $this;
    }

    /**
     * @param Folder|null $source
     * @return static
     */
    public function setSource($source = null)
    {
        parent::setSource($source);
        return $this;
    }

    /**
     * @return Access
     */
    protected function getAccess()
    {
        return $this->access;
    }
}
