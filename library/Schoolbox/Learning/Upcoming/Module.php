<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning\Upcoming;

use Alaress\Authorizer\Rule;
use Alaress_Date_Format;
use Alaress\Module\Module as BaseModule;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Aura\Di\ContainerInterface;
use Schoolbox\Role;
use Alaress\Twig\Loader\IChainLoader;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\UserMapper;

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
            '/{studentId:\d+}{/date}',
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
    public function initTemplatePaths(IChainLoader $templateLoader)
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
            $injector->lazyNew(
                Controller::class,
                [
                    'userMapper'            => $this->getUserMapper(),
                    'folderMapper'          => $this->getMapperFactory()->getLazy(FolderMapper::class),
                    'termMapper'            => $this->getMapperFactory()->getLazy(TermMapper::class),
                    'externalClass'         => $this->getInjector()->lazyGet('externalClass'),
                    'assessmentDataSource'  => $injector->lazyGet('assessmentDataSource'),
                    'participantDataSource' => $injector->lazyGet('participantDataSource'),
                    'defaultGradeGroup'     => $this->getInjector()->lazyGet('defaultGradeGroup'),
                ]
            )
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
