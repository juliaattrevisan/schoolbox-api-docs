<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Import\Profile;

use Alaress\Web\Controller\Base;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Controller extends Base
{
    /**
     * @var UserMapper
     */
    private $userMapper;


    /**
     * @var ErrorMapper
     */
    private $errorMapper;


    /**
     * Controller constructor.
     *
     * @param ErrorMapper $errorMapper
     * @param UserMapper  $userMapper
     */
    public function __construct(ErrorMapper $errorMapper, UserMapper $userMapper)
    {
        $this->errorMapper = $errorMapper;
        $this->userMapper = $userMapper;
    }


    /**
     * Provide form
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getIndex()
    {
        $this->getResponse()->content->set($this->buildHtmlOutput('@adminImportProfile/index.twig', [
        ])->setTitle('Import User Profile'));
    }


    /**
     * Process the form vars
     */
    public function postIndex()
    {
        $this->getResponse()->content->set($this->buildHtmlOutput('@adminImportProfile/submit.twig', [
            'var1' => $this->getRequest()->post['var1']
        ])->setTitle('Import User Profile'));
    }
}
