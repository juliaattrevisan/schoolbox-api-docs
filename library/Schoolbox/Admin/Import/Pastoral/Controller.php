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
use Schoolbox\Resource\Storage;
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
     * Start pastoral import in a detached state
     */
    public function postImport()
    {
        $post = $this->getRequest()->post;

        try {
            $defaultOwner = $this->getUserMapper()->findById($post["defaultOwnerId"]);
        } catch (\Exception $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_USER_NOT_FOUND);
        }

        // Do the import in a detached state so it doesn't time out

        $this->getResponse()->content->set([
            'template' => '@adminImportPastoral/importResults.twig',
            'data'     => array(
                'pageCurrent' => 'import'
            ),
            'postOutput' => [
                function() use ($defaultOwner) {
                    $this->importCallback($defaultOwner);
                }
            ],
        ]);
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
     */
    public function getLogDetail($id)
    {
        $this->getResponse()->content->set([
            'template' => '@adminImportPastoral/logDetail.twig',
            'data'     => array(
                'pageCurrent' => 'import',
                'logs' => $this->getLogMapper()->findByColumn("import_id", $id)->sortBy('createdAt', 'asc')
            )
        ]);
    }

    /**
     * Do pastoral record import in a detached state so it doesn't block the user
     *
     * @param \Schoolbox\User $defaultOwner         Fallback owner when cannot be located in Schoolbox
     */
    public function importCallback($defaultOwner)
    {
        try
        {
            $pastoralExternal = new \External_Pastoral();
            $pastoralExternal->importPastoral($this->getUser()->getId(), $defaultOwner);
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
