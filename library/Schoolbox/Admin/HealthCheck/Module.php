<?php

namespace Schoolbox\Admin\HealthCheck;

use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use Alaress\Authorizer\Rule;
use InvalidArgumentException;
use Schoolbox\User\Token\Mapper as TokenMapper;

class Module extends BaseModule
{
    protected $basename = 'adminHealthCheckExport';

    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     * @throws InvalidArgumentException
     */
    protected function registerRoutes(): void
    {
        $isAlaress = new Rule\IsSchoolboxAdmin();
        $this->register('GET', '/audit', 'getAudit', $isAlaress);
        // UXUI Audits
        $this->register('GET', '/audit/topMenuReport', 'topMenuReport', $isAlaress);
        $this->register('GET', '/audit/sideMenuReport', 'sideMenuReport', $isAlaress);
        // User Management Audits
        $this->register('GET', '/audit/listOfRoles', 'listOfRoles', $isAlaress);
        $this->register('GET', '/audit/listOfCampuses', 'listOfCampuses', $isAlaress);
        $this->register('GET', '/audit/listOfYearLevels', 'listOfYearLevels', $isAlaress);
        $this->register('GET', '/audit/listOfHouses', 'listOfHouses', $isAlaress);
        $this->register('GET', '/audit/servicePermissions', 'servicePermissions', $isAlaress);
        $this->register('GET', '/audit/listOfSuperusers', 'listOfSuperusers', $isAlaress);
        // LMS Fundamental Audits
        $this->register('GET', '/audit/gradeGroups', 'gradeGroups', $isAlaress);
        $this->register('GET', '/audit/academicPeriods', 'academicPeriods', $isAlaress);
        $this->register('GET', '/audit/workTypes', 'workTypes', $isAlaress);
        $this->register('GET', '/audit/submissionStates', 'submissionStates', $isAlaress);
        $this->register('GET', '/audit/lmsSettings', 'lmsSettings', $isAlaress);
        $this->register('GET', '/audit/yearLevelPermissions', 'yearLevelPermissions', $isAlaress);
        // Community Engagement Audits
        $this->register('GET', '/audit/newsTopics', 'newsTopics', $isAlaress);
        $this->register('GET', '/audit/newsServicePermissions', 'newsServicePermissions', $isAlaress);
        $this->register('GET', '/audit/newsSettings', 'newsSettings', $isAlaress);
        $this->register('GET', '/audit/eventTypes', 'eventTypes', $isAlaress);
        $this->register('GET', '/audit/calendarServicePermissions', 'calendarServicePermissions', $isAlaress);
        $this->register('GET', '/audit/parentNewsNotifications', 'parentNewsNotifications', $isAlaress);
        // Common Audits
        $this->register('GET', '/audit/homepageTemplates', 'homepageTemplates', $isAlaress);
        $this->register('GET', '/audit/resourceBrowser', 'resourceBrowser', $isAlaress);
        // SVA Reports
        $this->register('GET', '/audit/svaMonthlyLogins', 'svaMonthlyLogins', $isAlaress);
        $this->register('GET', '/audit/svaNewsArticleViews', 'svaNewsArticleViews', $isAlaress);
        $this->register('GET', '/audit/svaNewsArticleViewsPerRolePerArticle', 'svaNewsArticleViewsPerRolePerArticle', $isAlaress);
        $this->register('GET', '/audit/svaNewsArticleViewsPerMonthBySKC', 'svaNewsArticleViewsPerMonthBySKC', $isAlaress);
        $this->register('GET', '/audit/svaNewsArticleViewsPerMonthByActiveAlumni', 'svaNewsArticleViewsPerMonthByActiveAlumni', $isAlaress);
        $this->register('GET', '/audit/svaNewsCreatedBySVATeam', 'svaNewsCreatedBySVATeam', $isAlaress);
        $this->register('GET', '/audit/svaHomepageViewsByKSC', 'svaHomepageViewsByKSC', $isAlaress);
        $this->register('GET', '/audit/svaHomepageViewsByAllPerRolePerMonth', 'svaHomepageViewsByAllPerRolePerMonth', $isAlaress);
        $this->register('GET', '/audit/svaHomepageViewsForCommunityForumsPage', 'svaHomepageViewsForCommunityForumsPage', $isAlaress);
        $this->register('GET', '/audit/svaHomepageViewsForOpportunitiesPage', 'svaHomepageViewsForOpportunitiesPage', $isAlaress);
        $this->register('GET', '/audit/svaHomepageViewsForProfessionalReadingPage', 'svaHomepageViewsForProfessionalReadingPage', $isAlaress);
        $this->register('GET', '/audit/svaHomepageViewsForEvaluationPage', 'svaHomepageViewsForEvaluationPage', $isAlaress);
        $this->register('GET', '/audit/svaHomepageViewsForActiveAlumniPage', 'svaHomepageViewsForActiveAlumniPage', $isAlaress);
        $this->register('GET', '/audit/svaHomepageViewsForRrrHubSchoolsPage', 'svaHomepageViewsForRrrHubSchoolsPage', $isAlaress);
        $this->register('GET', '/audit/svaForumThreadsViews', 'svaForumThreadsViews', $isAlaress);
        $this->register('GET', '/audit/svaForumRepliesViews', 'svaForumRepliesViews', $isAlaress);
        // Implementation Status Review Reports
        $this->register('GET', '/audit/implementationServicePermissions', 'implementationServicePermissions', $isAlaress);
        $this->register('GET', '/audit/implementationRoleList', 'implementationRoleList', $isAlaress);
        $this->register('GET', '/audit/implementationCampusList', 'implementationCampusList', $isAlaress);
        $this->register('GET', '/audit/implementationHouseList', 'implementationHouseList', $isAlaress);
        $this->register('GET', '/audit/implementationYearLevels', 'implementationYearLevels', $isAlaress);
        $this->register('GET', '/audit/implementationNewsTopics', 'implementationNewsTopics', $isAlaress);
        $this->register('GET', '/audit/implementationCalendarEventTypes', 'implementationCalendarEventTypes', $isAlaress);
        $this->register('GET', '/audit/implementationNotificationSettings', 'implementationNotificationSettings', $isAlaress);
        // TODO: $this->register('GET', '/audit/implementationTopMenuReport', 'implementationCalendarEventTypes', $isAlaress);
        $this->register('GET', '/audit/implementationSideMenuReport', 'implementationSideMenuReport', $isAlaress);
        $this->register('GET', '/audit/implementationAcademicPeriods', 'implementationAcademicPeriods', $isAlaress);
        // Unit Planner Engagement Reports
        $this->register('GET', '/audit/unitplannerFrameworks', 'unitplannerFrameworks', $isAlaress);
        $this->register('GET', '/audit/unitplannerCourseTextboxes', 'unitplannerCourseTextboxes', $isAlaress);
        $this->register('GET', '/audit/unitplannerUnitTextboxes', 'unitplannerUnitTextboxes', $isAlaress);
        $this->register('GET', '/audit/unitplannerComponents', 'unitplannerComponents', $isAlaress);
        $this->register('GET', '/audit/unitplannerAssessments', 'unitplannerAssessments', $isAlaress);
        // OLCStats Reports
        $this->register('GET', '/audit/olcstatsQuizAttempts', 'olcstatsQuizAttempts', $isAlaress);
        $this->register('GET', '/audit/olcstatsTaskCompletions', 'olcstatsTaskCompletions', $isAlaress);
        $this->register('GET', '/audit/olcstatsDueWorkSubmissions', 'olcstatsDueWorkSubmissions', $isAlaress);
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->injector->get('user.tokenBuilder'),
            $this->getConfig(),
            $this->injector->get('router.generator'),
            $this->injector->get('taskd.messageFactory'),
            $this->injector->get('taskd.messageProducer'),
            $this->getMapperFactory()->get(TokenMapper::class)
        );
    }
}
