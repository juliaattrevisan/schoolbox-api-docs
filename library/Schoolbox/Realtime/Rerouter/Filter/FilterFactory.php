<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Realtime\Rerouter\Filter;


use Schoolbox\IConfigLoader;
use Schoolbox\Notif\UserQueue\UserQueueFactory;

class FilterFactory
{

    /** @var UserQueueFactory */
    protected $uqf;

    /** @var bool */
    protected $lite;

    /** @var IConfigLoader */
    protected $conf;


    /**
     * FilterFactory constructor.
     *
     * @param UserQueueFactory $uqf
     * @param IConfigLoader    $conf
     */
    public function __construct($uqf = null, $conf = null)
    {
        $this->setUserQueueFactory($uqf);
        $this->setConfig($conf);
        $this->setLiteMode(false);
    }


    /**
     * @param UserQueueFactory $uqf
     * @return self
     */
    public function setUserQueueFactory($uqf)
    {
        $this->uqf = $uqf instanceof UserQueueFactory ? $uqf : null;
        return $this;
    }


    /**
     * @param boolean $lite
     * @return self
     */
    public function setLiteMode($lite = true)
    {
        $this->lite = $lite;
        return $this;
    }


    /**
     * @param IConfigLoader $conf
     * @return self
     */
    public function setConfig($conf)
    {
        $this->conf = $conf instanceof IConfigLoader ? $conf : null;
        return $this;
    }


    /**
     * @return FilterBundle
     */
    public function build()
    {
        $bundle = new FilterBundle([ new AlreadyReroutedFilter() ]);

        if ($this->lite) {
            // only check we need for this is already-routed
            return $bundle;
        }

        if ($this->conf) {
            $bundle->attach(new MessagingEnabledFilter($this->conf));
        }

        $bundle->attachEach([
            new UserEnabledFilter(),
            new SelfSendFilter(),
            new CampusMembershipFilter(),
            new RoleMembershipFilter(),
            new RoletypeFilter(),
        ]);

        if ($this->uqf) {
            $bundle->attach(new DuplicateFilter($this->uqf));
        }

        return $bundle;
    }

}
