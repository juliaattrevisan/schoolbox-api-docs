<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Custom\Mggs\Personalised;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\ContainerInterface;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Assessment\Term;
use Schoolbox\Resource\Folder\ClassListFactory;
use Custom\Mggs\Personalised\Participant;
use Schoolbox\Mapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Role;
use Schoolbox\UserMapper;
use InvalidArgumentException;

/**
 * Sub-module for a student's personalised learning view.
 */
class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'learningPersonalised';

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

        $preload = function ($params) {
            $data = [];

            if ($params['studentId']) {
                $data['student'] = $this->getUserMapper()->findById($params['studentId']);
            }

            if ($params['date']) {
                try {
                    $data['term'] = $this->getMapperFactory()->get(Term\Mapper::class)->findForDate(
                        \Alaress_Date_Format::newUrlDate($params['date'])
                    );
                } catch (\InvalidArgumentException $e) {
                    // be friendly, but still stop here
                    throw new NotFound();
                }
            } else {
                $data['term'] = $this->getMapperFactory()->get(Term\Mapper::class)->findForToday();
            }

            return $data;
        };

        $this->register('GET', '', 'getPersonalised', new Rule\IsLoggedIn());
        $this->register(
            'GET',
            '/{studentId}',
            'getPersonalisedForStudent',
            $access,
            $preload
        );
        $this->register(
            'GET',
            '/resources/{studentId}{/date}',
            'getResourcesForStudent',
            $access,
            $preload
        );
        $this->register(
            'GET',
            '/calendar/{studentId}',
            'getCalendarForStudent',
            $access,
            $preload
        );
        $this->register(
            'POST',
            '/update/{studentId}',
            'postUpdateParticipation',
            $access,
            $preload
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
                    'folderMapper'         => $this->getMapperFactory()->getLazy(FolderMapper::class),
                    'assessmentDataSource' => $injector->lazyGet('assessmentDataSource'),
                    'participantMapper'    => $this->getParticipantMapper(),
                    'assessmentTranslator' => $injector->lazyGet('assessmentTranslator'),
                    'classListFactory'     => $injector->lazyNew(ClassListFactory::class, [
                        $injector->lazyGet('databaseInternal')
                    ]),
                ]
            )
        );
    }

    /* Getters / Setters
     */

    /**
     * @return UserMapper|Mapper
     * @throws InvalidArgumentException
     */
    protected function getUserMapper()
    {
        return $this->getMapperFactory()->get(UserMapper::class);
    }


    /**
     * @return Participant\Mapper|Mapper
     * @throws InvalidArgumentException
     */
    protected function getParticipantMapper()
    {
        return $this->getMapperFactory()->get(Participant\Mapper::class);
    }
}
