<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Course;


use Component_Instance;
use Schoolbox\Resource\Attachment\Zone;

class TaskBuilder
{

    /** @var string  */
    protected $id;

    /** @var string */
    protected $method;

    /** @var bool  */
    protected $selfAssessed;

    /** @var Component_Instance  */
    protected $component;

    /** @var string */
    protected $title;

    /** @var string  */
    protected $returnUrl;

    /** @var string */
    protected $url;

    /** @var Zone  */
    protected $zone;


    /**
     * TaskBuilder constructor.
     *
     * @param Component_Instance $component
     * @param Task               $task
     * @param string             $returnUrl
     */
    public function __construct(Component_Instance $component, Task $task = null, $returnUrl = '')
    {
        $this->setComponent($component);
        $this->setMethod($task ? 'modify' : 'create');
        $this->setId($task ? $task->getId() : "component{$component->getId()}");
        $this->setSelfAssessed($task ? !$task->getRequiresConfirmation() : true);
        $this->setTitle($task ? $task->getTitle() : '');
        $this->setReturnUrl($returnUrl);

        $zone = new Zone("az-{$this->getId()}");

        if ($task) {
            // decide whether to set attachment or just URL field
            if (preg_match('`^/storage/fetch.*([0-9a-f]{40})`i', $task->getLink(), $matches)) {
                $zone->attachFiles([ $matches[1] ]);
            } else {
                $this->setUrl($task->getLink());
            }
        }

        $this->setAttachZone($zone);
    }


    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }


    /**
     * @param string $id
     * @return static
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }


    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }


    /**
     * @param string $method
     * @return static
     */
    public function setMethod($method)
    {
        $this->method = $method;
        return $this;
    }


    /**
     * @return boolean
     */
    public function isSelfAssessed()
    {
        return $this->selfAssessed;
    }


    /**
     * @param boolean $selfAssessed
     * @return static
     */
    public function setSelfAssessed($selfAssessed)
    {
        $this->selfAssessed = $selfAssessed;
        return $this;
    }


    /**
     * @return Component_Instance
     */
    public function getComponent()
    {
        return $this->component;
    }


    /**
     * @param Component_Instance $component
     * @return static
     */
    public function setComponent($component)
    {
        $this->component = $component;
        return $this;
    }


    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }


    /**
     * @param string $title
     * @return static
     */
    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }


    /**
     * @return string
     */
    public function getReturnUrl()
    {
        return $this->returnUrl;
    }


    /**
     * @param string $returnUrl
     * @return static
     */
    public function setReturnUrl($returnUrl)
    {
        $this->returnUrl = $returnUrl;
        return $this;
    }


    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }


    /**
     * @param string $url
     * @return static
     */
    public function setUrl($url)
    {
        $this->url = $url;
        return $this;
    }


    /**
     * @return Zone
     */
    public function getAttachZone()
    {
        return $this->zone;
    }


    /**
     * @param Zone $zone
     * @return static
     */
    public function setAttachZone($zone)
    {
        $this->zone = $zone->setMulti(false);
        return $this;
    }

}
