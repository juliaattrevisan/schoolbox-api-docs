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
use Schoolbox\External;
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
     * @var External\Profile\Mapper
     */
    private $baseProfileMapper;


    /**
     * @var External\Profile\Parent\Mapper
     */
    private $parentProfileMapper;


    /**
     * @var External\Profile\Student\Mapper
     */
    private $studentProfileMapper;


    /**
     * @var External\Profile\Staff\Mapper
     */
    private $staffProfileMapper;


    /**
     * @var External\Profile\Relation\Mapper
     */
    private $relationMapper;


    /**
     * Controller constructor.
     *
     * @param External\Profile\Mapper          $baseMapper
     * @param External\Profile\Parent\Mapper   $parentMapper
     * @param External\Profile\Student\Mapper  $studentMapper
     * @param External\Profile\Staff\Mapper    $staffMapper
     * @param External\Profile\Relation\Mapper $relationMapper
     */
    public function __construct(
        External\Profile\Mapper $baseMapper,
        External\Profile\Parent\Mapper $parentMapper,
        External\Profile\Student\Mapper $studentMapper,
        External\Profile\Staff\Mapper $staffMapper,
        External\Profile\Relation\Mapper $relationMapper
    ) {
        $this->baseProfileMapper = $baseMapper;
        $this->parentProfileMapper = $parentMapper;
        $this->studentProfileMapper = $studentMapper;
        $this->staffProfileMapper = $staffMapper;
        $this->relationMapper = $relationMapper;
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
                    $importer = new Importer($this->getRequest()->post['file'], $attachment[0], $db);

                    /* Actually persist changes
                     */
                    if ((bool)$this->getRequest()->query['perform']) {
                        $importer->setPerform(true);
                    }

                    $logs = $importer->process()->getLog();
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


    /**
     * Export csv from db, for a given $type (student staff parent relation)
     *
     * @param string $type
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \InvalidArgumentException
     */
    public function getExport(string $type)
    {
        $content = (new Exporter(
            $type,
            $this->baseProfileMapper,
            $this->parentProfileMapper,
            $this->studentProfileMapper,
            $this->staffProfileMapper,
            $this->relationMapper
        ))->process();

        $headers = $this->getResponse()->headers;
        $headers->set('Pragma', ' ');
        $headers->set('Cache-Control', ' ');
        $headers->set('Content-Type', 'text/csv');
        $headers->set('Content-Length', \strlen($content));
        $headers->set('Content-Disposition', "attachment; filename=\"export-{$type}.csv\"");

        $this->getResponse()->content->set([
            'format' => 'file',
            'data'   => $content
        ]);
    }
}
