<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Realtime\Rerouter\Filter;


use Schoolbox\IConfigLoader;
use Schoolbox\Realtime\MessageList;


class FilterFactory
{

    /** @var IConfigLoader */
    protected $conf;
    /**
     * @var MessageList\IFactory
     */
    private $factory;


    /**
     * @param MessageList\IFactory $factory
     * @param IConfigLoader        $conf
     */
    public function __construct(MessageList\IFactory $factory = null, IConfigLoader $conf = null)
    {
        $this->factory = $factory;
        $this->conf    = $conf;
    }


    /**
     * @return FilterBundle
     */
    public function build()
    {
        $bundle = new FilterBundle([ new AlreadyReroutedFilter() ]);

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

        if ($this->factory) {
            $bundle->attach(new DuplicateFilter($this->factory));
        }

        return $bundle;
    }

}
