<?php

namespace Schoolbox\L10n;

use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use Alaress\Authorizer\Rule;

class Module extends BaseModule
{
    protected $basename = 'l10n';

    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        $perms = new Rule\IsLoggedIn();
        $this->register('GET', '', 'getLocale', $perms);
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        $rosettaStone = (new RosettaStoneFactory())->build(Mapper::getInstance());
        return new Controller($rosettaStone);
    }
}
