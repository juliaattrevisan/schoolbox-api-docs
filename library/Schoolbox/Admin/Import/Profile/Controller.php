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
            'allBreadcrumbs' => $this->buildBreadcrumbs($this->getRouter()),
            'allNavigation' => $this->buildNavigation($this->getRouter(), 'adminImportProfile')->getAllNodes(),
            'attachzone' => [
                'student' => $this->getAttachZone('student', 'Upload Student CSV file'),
                'parent' => $this->getAttachZone('parent', 'Upload Parent CSV file'),
                'relation' => $this->getAttachZone('relation', 'Upload Relationship CSV file'),
                'staff' => $this->getAttachZone('staff', 'Upload Staff CSV file'),
            ]
        ])->setTitle('Import User Profile'));
    }


    /**
     * Insert an attach zone into the template
     *
     * @param string $id
     * @param string $prompt
     * @return array
     * @throws \UnexpectedValueException
     */
    private function getAttachZone(string $id, string $prompt): array
    {
        return (new Zone($id))
            ->setModalTitle('Select CSV Files')
            ->setAttachmentsLabel('')
            ->setAttachPrompt($prompt)
            ->setMimeFilter('text/csv')
            ->setReadOnly(false)
            ->setMulti(false)
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
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    public function postUpload()
    {
        /** @var IConnector $db */
        $db = $this->getDi()->get('databaseInternal');

        $collectorFactory = new CollectorFactory();

        $logs = [];
        $errors = [];

        if (!$this->getRequest()->post['file']) {
            $errors[] = 'Please choose a csv type.';
        } else {

            /* Process attachment specifically
             */
            if ($this->getRequest()->post['attachment']) {
                $attachment = $collectorFactory->build($this->getRequest()->post['attachment'])->getAll();

                try {
                    $processor = new Processor($this->getRequest()->post['file'], $attachment[0], $db);

                    /* Actually persist changes
                     */
                    if ((bool)$this->getRequest()->query['perform']) {
                        $processor->setPerform(true);
                    }

                    $logs = $processor->process()->getLog();
                } catch (\InvalidArgumentException $e) {
                    $errors[] = $e->getMessage();
                }
            } else {
                $errors[] = 'No files were supplied.';
            }
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@adminImportProfile/log.twig', [
            'allBreadcrumbs' => $this->buildBreadcrumbs($this->getRouter()),
            'allNavigation' => $this->buildNavigation($this->getRouter(), 'adminImportProfile')->getAllNodes(),
            'attachment' => $this->getRequest()->post['attachment'],
            'errors' => $errors,
            'logs' => $logs,
            'file' => $this->getRequest()->post['file'],
            'perform' => (bool)$this->getRequest()->query['perform']
        ])->setTitle('Import User Profile'));
    }


    public function getExport(string $type)
    {
        /** @var IConnector $db */
        $db = $this->getDi()->get('databaseInternal');

        $exporter = new Exporter($type, $db);

        $exporter->process();
    }

}
