<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Import\Profile;

use Alaress\Database\Connector\IConnector;
use Alaress\Web\Controller\Base;
use Schoolbox\Import\ImportNavigation;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Attachment\Zone;

/**
 * Class Controller
 *
 * Import User Profiles from four csvs:
 *    student.csv
 *    parent.csv
 *    staff.csv
 *    relation.csv
 *
 * @package Schoolbox\Admin\Import\Profile
 */
class Controller extends Base
{
    use ImportNavigation;

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
     *
     * @throws MapperException
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \InvalidArgumentException
     * @throws \ReflectionException
     * @throws \RuntimeException
     * @throws \UnexpectedValueException
     * @throws \http\Exception\InvalidArgumentException
     */
    public function postUpload()
    {
        /** @var IConnector $db */
        $db = $this->getDi()->get('databaseInternal');

        $attachments = (new CollectorFactory())->build($this->getRequest()->post['attachment'])->getAll();

        $processor = (new Processor($attachments, $db))->process();

        $this->getResponse()->content->set($this->buildHtmlOutput('@adminImportProfile/log.twig', [
            'allNavigation' => $this->buildNavigation($this->getRouter(), 'adminImportProfile')->getAllNodes(),
            'log' => $processor->getLog()
        ])->setTitle('Import User Profile'));
    }
}
