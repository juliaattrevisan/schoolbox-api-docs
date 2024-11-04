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

        $rulePastoral = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasServicePermission(UserAccess::SERVICE_PASTORAL, UserAccess::ACCESS_FULL)
            )
        );

        $ruleLms = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasServicePermission(UserAccess::SERVICE_LMS, UserAccess::ACCESS_FULL)
            )
        );

        $ruleBadge = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasServicePermission(UserAccess::SERVICE_BADGE, UserAccess::ACCESS_FULL)
            )
        );

        $ruleDesign = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasServicePermission(UserAccess::SERVICE_DESIGN, UserAccess::ACCESS_FULL)
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

        // Routes to which users with service permission UserAccess::SERVICE_PASTORAL need access.
        $pastoralTablesRegex = '(pastoral-action|pastoral-severity|pastoral-tags|pastoral-document-classification|pastoral-category|pastoral-type)';
        $this->register('GET', "/pastoral/{table:$pastoralTablesRegex}", 'getPastoralIndex', $rulePastoral);
        $this->register('GET', "/pastoral/data/{table:$pastoralTablesRegex}", 'getPastoralData', $rulePastoral);
        $this->register('POST', "/pastoral/save/{table:$pastoralTablesRegex}", 'postPastoralSave', $rulePastoral);
        $this->register('POST', "/pastoral/delete/{table:$pastoralTablesRegex}", 'postPastoralDelete', $rulePastoral);

        // Routes to which users with service permission UserAccess::SERVICE_LMS need access.
        $lmsTablesRegex = '(submission-type|submission-errors)';
        $this->register('GET', "/lms/{table:$lmsTablesRegex}", 'getLmsIndex', $ruleLms);
        $this->register('GET', "/lms/data/{table:$lmsTablesRegex}", 'getLmsData', $ruleLms);
        $this->register('POST', "/lms/save/{table:$lmsTablesRegex}", 'postLmsSave', $ruleLms);
        $this->register('POST', "/lms/delete/{table:$lmsTablesRegex}", 'postLmsDelete', $ruleLms);

        // Routes to which users with service permission UserAccess::SERVICE_BADGE need access.
        $this->register('GET', "/badge/{table:achievement-category}", 'getBadgeIndex', $ruleBadge);
        $this->register('GET', "/badge/data/{table:achievement-category}", 'getBadgeData', $ruleBadge);
        $this->register('POST', "/badge/save/{table:achievement-category}", 'postBadgeSave', $ruleBadge);
        $this->register('POST', "/badge/delete/{table:achievement-category}", 'postBadgeDelete', $ruleBadge);

        // Routes to which users with service permission UserAccess::SERVICE_DESIGN need access.
        $this->register('GET', "/design/{table:component-definition}", 'getDesignIndex', $ruleDesign);
        $this->register('GET', "/design/data/{table:component-definition}", 'getDesignData', $ruleDesign);
        $this->register('POST', "/design/save/{table:component-definition}", 'postDesignSave', $ruleDesign);
        $this->register('POST', "/design/delete/{table:component-definition}", 'postDesignDelete', $ruleDesign);

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
