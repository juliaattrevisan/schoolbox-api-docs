<?php
/**
 * Pastoral import page
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Admin\Import\Pastoral;

use Alaress\Web\Controller\Base;
use Schoolbox\Pastoral\Log\Mapper as LogMapper;
use Schoolbox\User;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\UserMapper;

class Controller extends Base
{
    /**
     * @var ConfigMapper
     */
    protected $configMapper;

    /**
     * @var LogMapper
     */
    protected $logMapper;

    /**
     * @var UserMapper
     */
    protected $userMapper;

    /**
     * @var ErrorMapper
     */
    protected $errorMapper;

    /***
     * @return ErrorMapper
     */
    public function getErrorMapper() {
        return $this->errorMapper;
    }

    /**
     * @param ErrorMapper $errorMapper
     * @return $this
     */
    public function setErrorMapper($errorMapper) {
        $this->errorMapper = $errorMapper;
        return $this;
    }

    /***
     * @return ConfigMapper
     */
    public function getConfigMapper() {
        return $this->configMapper;
    }

    /**
     * @param ConfigMapper $configMapper
     * @return $this
     */
    public function setConfigMapper($configMapper) {
        $this->configMapper = $configMapper;
        return $this;
    }

    /***
     * @return UserMapper
     */
    public function getUserMapper() {
        return $this->userMapper;
    }

    /**
     * @param UserMapper $userMapper
     * @return $this
     */
    public function setUserMapper($userMapper) {
        $this->userMapper = $userMapper;
        return $this;
    }

    /***
     * @return LogMapper
     */
    public function getLogMapper() {
        return $this->logMapper;
    }

    /**
     * @param LogMapper $logMapper
     * @return $this
     */
    public function setLogMapper($logMapper) {
        $this->logMapper = $logMapper;
        return $this;
    }

    /**
     * Controller constructor.
     */
    public function __construct()
    {
        $this->setConfigMapper(ConfigMapper::getInstance());
        $this->setErrorMapper(ErrorMapper::getInstance());
        $this->setUserMapper(UserMapper::getInstance());
        $this->setLogMapper(LogMapper::getInstance());
    }

    /**
     * Display list of available logs
     */
    public function getLog()
    {
        $this->getResponse()->content->set([
            'template' => '@adminImportPastoral/log.twig',
            'data'     => array(
                'pageCurrent' => 'import',
                'logs' => $this->getLogMapper()->findImportIds()
            )
        ]);
    }

    /**
     * Display list of available logs
     *
     * @param int $id Pastoral import log ID
     */
    public function getLogDetail($log)
    {
        $this->getResponse()->content->set([
            'template' => '@adminImportPastoral/logDetail.twig',
            'data'     => array(
                'pageCurrent' => 'import',
                'log' => $log
            )
        ]);
    }

    /**
     * Start pastoral import in a detached state
     */
    public function postImport()
    {
        $post = $this->getRequest()->post;

        try {
            $defaultOwner = $this->getUserMapper()->findById($post["defaultOwnerId"]);
            $importParentAccess = $post["importParentAccess"];
        } catch (\Exception $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_USER_NOT_FOUND);
        }

        // Do the import in a detached state so it doesn't time out

        $this->getResponse()->content->set([
            'template' => '@adminImportPastoral/log.twig',
            'data'     => array(
                'pageCurrent'   => 'import',
                'logs'          => $this->getLogMapper()->findImportIds(),
                'message'       => true
            ),
            'postOutput' => [
                function() use ($defaultOwner, $importParentAccess) {
                    $this->importCallback($defaultOwner, $importParentAccess);
                }
            ],
        ]);
    }

    /**
     * Do pastoral record import in a detached state so it doesn't block the user
     *
     * @param User $defaultOwner Fallback owner when cannot be located in Schoolbox
     * @param bool $importParentAccess Flag indicating if parents should be imported to the pastoral_access table
     */
    public function importCallback($defaultOwner, $importParentAccess)
    {
        try
        {
            $pastoralExternal = new \External_Pastoral();
            $pastoralExternal->importPastoral($this->getUser(), $defaultOwner, $importParentAccess);
        }
        catch (\Exception $e)
        {
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

    public function getIndex()
    {
        // Render this thing out

        $this->getResponse()->content->set(
            array(
                'template' => '@adminImportPastoral/index.twig',
                'data'     => array(
                    'pageCurrent' => 'import',
                    'externalEnabled' => $this->getConfigMapper()->get("external_pastoral")
                ),
            )
        );
    }
}
