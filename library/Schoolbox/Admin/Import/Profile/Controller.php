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
use Schoolbox\Admin\Import\Navigation\INavigation;
use Schoolbox\Admin\Import\Navigation\SchoolData;
use Schoolbox\External;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\Resource\Storage\FilesystemProxy;

/**
 * Class Controller
 *
 * Import User Profiles from four csvs:
 *    student.csv
 *    parent.csv
 *    staff.csv
 *    relationship.csv
 *
 * @package Schoolbox\Admin\Import\Profile
 */
class Controller extends Base
{

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
     * @var External\Profile\Relationship\Mapper
     */
    private $relationshipMapper;


    /**
     * @var INavigation
     */
    protected $navigation;


    /**
     * Controller constructor.
     *
     * @param External\Profile\Mapper              $baseMapper
     * @param External\Profile\Parent\Mapper       $parentMapper
     * @param External\Profile\Student\Mapper      $studentMapper
     * @param External\Profile\Staff\Mapper        $staffMapper
     * @param External\Profile\Relationship\Mapper $relationshipMapper
     * @param FilesystemProxy                      $filesystemProxy
     */
    public function __construct(
        External\Profile\Mapper $baseMapper,
        External\Profile\Parent\Mapper $parentMapper,
        External\Profile\Student\Mapper $studentMapper,
        External\Profile\Staff\Mapper $staffMapper,
        External\Profile\Relationship\Mapper $relationshipMapper,
        private FilesystemProxy $filesystemProxy,
    ) {
        $this->baseProfileMapper = $baseMapper;
        $this->parentProfileMapper = $parentMapper;
        $this->studentProfileMapper = $studentMapper;
        $this->staffProfileMapper = $staffMapper;
        $this->relationshipMapper = $relationshipMapper;

        $this->navigation = new SchoolData();
    }

    /**
     * Provide form
     *
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \ReflectionException
     * @throws \UnexpectedValueException
     */
    public function getIndex()
    {
        $this->getResponse()->content->set($this->buildHtmlOutput('@adminImportProfile/index.twig', [
            'pageCurrent' => $this->router->generate('adminImportProfile.getIndex'),
            'allBreadcrumbs' => $this->navigation->buildBreadcrumbs($this->getRouter()),
            'allNavigation' => $this->navigation->buildNavigation($this->getRouter(), 'adminImportProfile')->getAllNodes(),
            'attachzone' => [
                Mapping::CSV_STUDENT => $this->getAttachZone(Mapping::CSV_STUDENT, 'Upload Student CSV file'),
                Mapping::CSV_PARENT => $this->getAttachZone(Mapping::CSV_PARENT, 'Upload Parent CSV file'),
                Mapping::CSV_RELATIONSHIP => $this->getAttachZone(Mapping::CSV_RELATIONSHIP, 'Upload Relationship CSV file'),
                Mapping::CSV_STAFF => $this->getAttachZone(Mapping::CSV_STAFF, 'Upload Staff CSV file'),
            ],

            /* Check which tables have data; if there is any content, link to the exporter
             */
            'profiles' => [
                'numParents' => $this->parentProfileMapper->countAll(),
                'numStaff' => $this->staffProfileMapper->countAll(),
                'numStudents' => $this->studentProfileMapper->countAll(),
                'numRelationships' => $this->relationshipMapper->countAll()
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
            ->setMimeFilter('*/*')
            ->setReadOnly(false)
            ->setMulti(false)
            ->getTwigContext();
    }


    /**
     * Process the form vars
     *
     * @throws MapperException
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
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
                    $importer = new Importer(
                        $this->getRequest()->post['file'],
                        $attachment[0],
                        $db,
                        $this->baseProfileMapper,
                        $this->parentProfileMapper,
                        $this->studentProfileMapper,
                        $this->staffProfileMapper,
                        $this->relationshipMapper,
                        null,
                        $this->filesystemProxy,
                    );

                    /* Actually persist changes
                     */
                    if ((bool)$this->getRequest()->query->get('perform')) {
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
            'allBreadcrumbs' => $this->navigation->buildBreadcrumbs($this->getRouter()),
            'allNavigation' => $this->navigation->buildNavigation($this->getRouter(), 'adminImportProfile')->getAllNodes(),
            'attachment' => $this->getRequest()->post['attachment'],
            'errors' => $errors,
            'logs' => $logs,
            'file' => $this->getRequest()->post['file'],
            'perform' => (bool)$this->getRequest()->query->get('perform')
        ])->setTitle('Import User Profile'));
    }


    /**
     * Export csv from db, for a given $type (student staff parent relationship)
     *
     * @param string $type
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
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
            $this->relationshipMapper
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
