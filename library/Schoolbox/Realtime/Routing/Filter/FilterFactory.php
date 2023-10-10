<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Routing\Filter;

use Schoolbox\IConfigLoader;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Realtime\MessageList;
use Schoolbox\Realtime\MessageList\IListFactory;

class FilterFactory
{
    /**
     * @param IListFactory|null  $factory
     * @param IConfigLoader|null $conf
     */
    public function __construct(private ?MessageList\IListFactory $factory = null, protected ?IConfigLoader $conf = null)
    {
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
            new AccessFilter(Access::getInstance()),
        ]);

        if ($this->factory) {
            $bundle->attach(new DuplicateFilter($this->factory));
        }

        return $bundle;
    }
}
