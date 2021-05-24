<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Routing\Filter;

use Schoolbox\IConfigLoader;
use Schoolbox\Realtime\MessageList;

class FilterFactory
{

    /** @var IConfigLoader */
    protected $conf;
    /**
     * @var MessageList\IListFactory
     */
    private $factory;


    /**
     * @param MessageList\IListFactory $factory
     * @param IConfigLoader            $conf
     */
    public function __construct(MessageList\IListFactory $factory = null, IConfigLoader $conf = null)
    {
        $this->factory = $factory;
        $this->conf    = $conf;
    }


    /**
     * @return FilterBundle
     */
    public function build()
    {
        $bundle = new FilterBundle([]);

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
