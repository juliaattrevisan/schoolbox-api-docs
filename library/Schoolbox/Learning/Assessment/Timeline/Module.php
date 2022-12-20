<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Timeline;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use InvalidArgumentException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Schoolbox\Application\Exception\Exception;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Authorizer\Rule\IsInstructor;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\UserMapper;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'learningAssessmentTimeline';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        /* Staff can access the fulltimeline
         */
        $fulltimelinePerms = new Rule\Any(
            new Rule\IsSuperuser(),
            new IsInstructor(Access::getInstance())
        );

        $preloadAssessmentUser = function ($params) {
            return $this->preloadAssessmentUser($params);
        };

        $this->register('GET', '/{assessmentId}/user/{userId}', 'getFullTimeline', $fulltimelinePerms, $preloadAssessmentUser);
    }


    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller();
    }


    /**
     * Preload a User instance
     *
     * @param array $params
     * @return array
     * @throws InvalidArgumentException
     * @throws NotFound
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Exception
     */
    private function preloadAssessmentUser(array $params): array
    {
        try {
            $student = $this->getMapperFactory()->get(UserMapper::class)->findById($params['userId']);
        } catch (MapperException $e) {
            throw new NotFound();
        }

        try {
            $assessment = $this->injector->get('assessmentDataSource')->findByIdForReview($params['assessmentId'], $student);
        } catch (MapperException\Deleted $e) {
            $assessment = $e->getDeletedObject();
        } catch (MapperException $e) {
            // Possibly thrown by base findByOne() mapper call, but masked by overriding @method annotation
            throw $this->getMapperFactory()->get(ErrorMapper::class)->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
        }

        return [
            'assessment' => $assessment,
            'student' => $student,
        ];
    }
}
