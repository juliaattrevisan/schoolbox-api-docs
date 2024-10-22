<?php

/**
 * Course builder admin page
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Lists;

use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule;
use Alaress\Web\Controller\Base;
use Alaress\Twig\Loader\IChainLoader;
use Schoolbox\Permission\Access\UserAccess;

class Module extends BaseModule
{
    protected $basename = 'adminLists';

    /**
     * @return void
     */
    public function registerRoutes(): void
    {
        $ruleSuperuserOnly = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\IsSuperuser(),
        );

        $ruleNews = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasServicePermission(UserAccess::SERVICE_NEWS, UserAccess::ACCESS_FULL)
            )
        );

        $ruleCalendar = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasServicePermission(UserAccess::SERVICE_CALENDAR, UserAccess::ACCESS_FULL)
            )
        );

        // Routes to which users with service permission UserAccess::SERVICE_NEWS need access.
        $this->register('GET', "/news/{table:news-topic}", 'getNewsIndex', $ruleNews);
        $this->register('GET', "/news/data/{table:news-topic}", 'getNewsData', $ruleNews);
        $this->register('POST', "/news/save/{table:news-topic}", 'postNewsSave', $ruleNews);
        $this->register('POST', "/news/delete/{table:news-topic}", 'postNewsDelete', $ruleNews);

        // Routes to which users with service permission UserAccess::SERVICE_CALENDAR need access.
        $this->register('GET', "/calendar/{table:event-type}", 'getCalendarIndex', $ruleCalendar);
        $this->register('GET', "/calendar/data/{table:event-type}", 'getCalendarData', $ruleCalendar);
        $this->register('POST', "/calendar/save/{table:event-type}", 'postCalendarSave', $ruleCalendar);
        $this->register('POST', "/calendar/delete/{table:event-type}", 'postCalendarDelete', $ruleCalendar);

        // Routes to which only superusers need access.
        $this->register('GET', "{/table}", 'getIndex', $ruleSuperuserOnly);
        $this->register('GET', "/data{/table}", 'getData', $ruleSuperuserOnly);
        $this->register('POST', "/save", 'postSave', $ruleSuperuserOnly);
        $this->register('POST', "/delete", 'postDelete', $ruleSuperuserOnly);
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller($this->getMapperFactory());
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
