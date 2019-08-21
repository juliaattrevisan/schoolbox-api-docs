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
use Schoolbox\Import\ImportNavigation;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Controller extends Base
{

    use ImportNavigation;


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
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \ReflectionException
     * @throws \UnexpectedValueException
     */
    public function getIndex()
    {
        $this->getResponse()->content->set($this->buildHtmlOutput('@adminImportProfile/index.twig', [
            'pageCurrent' => 'import',
            'allNavigation'  => $this->buildNavigation($this->getRouter(), 'adminImportProfile')->getAllNodes(),
            'attachzone' => $this->getAttachZone(),
        ])->setTitle('Import User Profile'));
    }


    /**
     * Insert an attach zone into the template
     *
     * @return array
     * @throws \UnexpectedValueException
     */
    private function getAttachZone(): array
    {
        return (new Zone('attachment'))
            ->setModalTitle('Select CSV Files')
            ->setAttachmentsLabel('')
            ->setAttachPrompt('Upload CSV Files')
            ->setMimeFilter('text/csv')
            ->setReadOnly(false)
            ->setMulti(true)
            ->getTwigContext();
    }


    /**
     * Process the form vars
     */
    public function postUpload()
    {
        $attachments = (new CollectorFactory())->build($this->getRequest()->post['attachment'])->getAll();

        new \Alaress_Probe($attachments);

        die();

        $this->getResponse()->content->set($this->buildHtmlOutput('@adminImportProfile/submit.twig', [
        ])->setTitle('Import User Profile'));
    }
}
