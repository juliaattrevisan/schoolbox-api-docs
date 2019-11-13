<?php
/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
namespace Schoolbox\Admin\Import\Pastoral;

use Alaress\Web\Controller\Base;
use Exception;
use External_Pastoral;
use Schoolbox\Admin\Import\Navigation\Direct;
use Schoolbox\Admin\Import\Navigation\INavigation;
use Schoolbox\Collection;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Pastoral\Log\Mapper as PastoralLogMapper;
use Schoolbox\Pastoral\Log\Model as PastoralLog;
use Schoolbox\User;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\UserMapper;

/**
 * Class Controller
 *
 * Pastoral import page
 *
 * @package Schoolbox\Admin\Import\Pastoral
 */
class Controller extends Base
{

    /** @var PastoralLogMapper */
    protected $pastoralLogMapper;

    /** @var UserMapper */
    protected $userMapper;

    /** @var ErrorMapper */
    protected $errorMapper;


    /**
     * @var INavigation
     */
    protected $navigation;


    /**
     * Controller constructor.
     *
     * @param IConfigLoader $configLoader
     */
    public function __construct(IConfigLoader $configLoader)
    {
        $this->setErrorMapper(ErrorMapper::getInstance());
        $this->setUserMapper(UserMapper::getInstance());
        $this->setPastoralLogMapper(PastoralLogMapper::getInstance());

        $this->navigation = new Direct($configLoader);
    }

    /**
     * Display import page
     */
    public function getIndex()
    {
        $this->getResponse()->content->set(
            [
                'template' => '@adminImportPastoral/index.twig',
                'data'     => [
                    'allBreadcrumbs' => $this->navigation->buildBreadcrumbs($this->getRouter()),
                    'allNavigation'  => $this->navigation->buildNavigation($this->getRouter(), 'adminImportPastoral')->getAllNodes(),
                    'externalEnabled' => $this->getConfigLoader()->get("external_pastoral"),
                ],
            ]
        );
    }

    /**
     * Display list of available logs
     */
    public function getLog()
    {
        $this->getResponse()->content->set([
            'template' => '@adminImportPastoral/log.twig',
            'data'     => [
                'allBreadcrumbs' => $this->navigation->buildBreadcrumbs($this->getRouter()),
                'allNavigation'  => $this->navigation->buildNavigation($this->getRouter(), 'adminImportPastoral')->getAllNodes(),
                'logs'           => $this->getPastoralLogMapper()->findImportIds(),
            ],
        ]);
    }

    /**
     * Display log entries for the selected log
     *
     * @param Collection|PastoralLog[] $log Pastoral log
     */
    public function getLogDetail($log)
    {
        $this->getResponse()->content->set([
            'template' => '@adminImportPastoral/logDetail.twig',
            'data'     => [
                'allBreadcrumbs' => $this->navigation->buildBreadcrumbs($this->getRouter()),
                'allNavigation'  => $this->navigation->buildNavigation($this->getRouter(), 'adminImportPastoral')->getAllNodes(),
                'log'            => $log,
            ],
        ]);
    }

    /**
     * Start pastoral import in a detached state
     */
    public function postImport()
    {
        $post = $this->getRequest()->post;

        try {
            $defaultOwner       = $this->getUserMapper()->findById($post["defaultOwnerId"]);
            $importParentAccess = $post["importParentAccess"] === 'on';
            $importRecords      = $post["importRecords"] === 'on';
            $deleteRecords      = $post["deleteRecords"] === 'on';
            $importAttachments  = $post["importAttachments"] === 'on';
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_USER_NOT_FOUND);
        }

        // We must cache the session user if used in post-detach callbacks as session no longer exists at that point
        $sessionUser = $this->getUser();

        // Do the import in a detached state so it doesn't time out
        $this->getResponse()->content->set([
            'template'   => '@adminImportPastoral/log.twig',
            'data'       => [
                'allBreadcrumbs' => $this->navigation->buildBreadcrumbs($this->getRouter()),
                'allNavigation'  => $this->navigation->buildNavigation($this->getRouter(), 'adminImportPastoral')->getAllNodes(),
                'logs'           => $this->getPastoralLogMapper()->findImportIds(),
                'message'        => true,
            ],
            'postOutput' => [
                function () use ($sessionUser, $defaultOwner, $importParentAccess, $importRecords, $importAttachments, $deleteRecords) {
                    $this->importCallback($sessionUser, $defaultOwner, $importParentAccess, $importRecords, $importAttachments, $deleteRecords);
                },
            ],
        ]);
    }

    /**
     * Do pastoral record import in a detached state so it doesn't block the user
     *
     * @param User $user               Schoolbox user doing the import
     * @param User $defaultOwner       Fallback owner when cannot be located in Schoolbox
     * @param bool $importParentAccess Flag indicating if parents should be imported to the pastoral_access table
     */
    public function importCallback(User $user, User $defaultOwner, $importParentAccess, $importRecords, $importAttachments, $deleteRecords)
    {
        try {
            $pastoralExternal = new External_Pastoral();
            $pastoralExternal->importPastoral($user, $defaultOwner, $importParentAccess, $importRecords, $importAttachments, $deleteRecords);
        } catch (Exception $e) {
            //                                  .
            //                                 -|-
            //                                  |
            //                              .-'~~~`-.
            //                            .'         `.
            //                            |  R  I  P  |
            //                            |           |
            //                            |    S B    |
            //                          \\|           |//
            // ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
            //
            // "I'm sorry- the client has detached, there is nothing more we can do"
        }
    }

    // Getters & Setters

    /***
     * @return ErrorMapper
     */
    public function getErrorMapper()
    {
        return $this->errorMapper;
    }

    /**
     * @param ErrorMapper $errorMapper
     *
     * @return self|$this
     */
    public function setErrorMapper($errorMapper)
    {
        $this->errorMapper = $errorMapper;

        return $this;
    }

    /***
     * @return UserMapper
     */
    public function getUserMapper()
    {
        return $this->userMapper;
    }

    /**
     * @param UserMapper $userMapper
     *
     * @return self|$this
     */
    public function setUserMapper($userMapper)
    {
        $this->userMapper = $userMapper;

        return $this;
    }

    /***
     * @return PastoralLogMapper
     */
    public function getPastoralLogMapper()
    {
        return $this->pastoralLogMapper;
    }

    /**
     * @param PastoralLogMapper $pastoralLogMapper
     *
     * @return self|$this
     */
    public function setPastoralLogMapper($pastoralLogMapper)
    {
        $this->pastoralLogMapper = $pastoralLogMapper;

        return $this;
    }
}
