<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning\Upcoming;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress_Date_Format;
use Alaress\Module\Module as BaseModule;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Alaress\Web\Router\PathMatcher;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Schoolbox\Role;
use Twig_Loader_Chain;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Mapper as GradeGroupMapper;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\UserMapper;
use Schoolbox\Error\Mapper as ErrorMapper;

/**
 * Sub-module for a student's upcoming due work.
 */
class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'learningUpcoming';

    /**
     * @inheritdoc
     */
    public function registerRoutes()
    {
        // Permissions:
        // All users may view their own work
        // staff may view anyone's due work
        $access = new Rule\Any(
            new Rule\IsUser('studentId'),
            new Rule\HasRole(Role::STAFF)
        );
        // parents may view their child's work only if config.restrict_parent_duework_access is off
        if (!$this->getConfigMapper()->get('restrict_parent_duework_access')) {
            $access->addRule(new Rule\IsParentOfUser($this->getExternalUser(), $this->getUserMapper(), 'studentId'));
        }

        $this->register('GET', '', 'getUpcoming', new Rule\IsLoggedIn());
        $this->register(
            'GET',
            '/{studentId}{/date}',
            'getUpcomingForStudent',
            $access,
            function ($params) {

                // Build a date
                if (isset($params['date'])) {
                    try {
                        $date = Alaress_Date_Format::newUrlDate($params['date']);
                    } catch (\Exception $e) {
                        // noop
                    }
                }
                if (!isset($date)) {
                    $date = Alaress_Date_Format::now();
                }

                // Pass the date and student to the controller action
                return [
                    'student' => $this->getUserMapper()->findById($params['studentId']),
                    'date'    => $date,
                ];
            }
        );
    }

    /**
     * @inheritdoc
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:' . $this->basename,
            $injector->lazyNew(Controller::class)
        );
    }

    /* Getters / Setters
     */

    /**
     * @return UserMapper
     */
    protected function getUserMapper()
    {
        return $this->getMapperFactory()->get(UserMapper::class);
    }

    /**
     * @return ConfigMapper
     */
    protected function getConfigMapper()
    {
        return $this->getMapperFactory()->get(ConfigMapper::class);
    }
}
