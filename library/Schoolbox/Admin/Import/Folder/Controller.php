<?php

/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\Import\Folder;

use Alaress\Database\Predicate\InvalidColumnException;
use Alaress\Web\Controller\Base;
use Bulk_Folder;
use InvalidArgumentException;
use PDOException;
use Schoolbox\Admin\Import\Navigation\Direct;
use Schoolbox\Admin\Import\Navigation\INavigation;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Application;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\RoleMapper;
use Schoolbox\Resource;
use Schoolbox\Resource\HomepageTemplateMapper;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Error\Mapper as ErrorMapper;
use UnexpectedValueException;

/**
 * Class Controller
 *
 * Bulk add folders admin page
 *
 * @package Schoolbox\Admin\Import\Folder
 */
class Controller extends Base
{
    /**
     * @var RoleMapper
     */
    private $roleMapper;


    /**
     * @var HomepageTemplateMapper
     */
    private $homepageTemplateMapper;


    /**
     * @var ErrorMapper
     */
    private $errorMapper;


    /**
     * @var INavigation
     */
    protected $navigation;


    /**
     * Controller constructor.
     *
     * @param RoleMapper                       $roleMapper
     * @param ErrorMapper                      $errorMapper
     * @param HomepageTemplateMapper           $homepageTemplateMapper
     * @param IConfigLoader                    $configLoader
     * @param Resource\Storage\FilesystemProxy $filesystemProxy
     */
    public function __construct(
        RoleMapper $roleMapper,
        ErrorMapper $errorMapper,
        HomepageTemplateMapper $homepageTemplateMapper,
        IConfigLoader $configLoader,
        private Resource\Storage\FilesystemProxy $filesystemProxy,
    ) {
        $this->roleMapper             = $roleMapper;
        $this->errorMapper            = $errorMapper;
        $this->homepageTemplateMapper = $homepageTemplateMapper;

        $this->navigation = new Direct($configLoader);
    }


    /**
     * Display bulk upload admin page
     *
     * @api   GET /adminv2/import/folder
     * @since 16.0.0
     * @throws UnexpectedValueException
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \ReflectionException
     */
    public function getIndex(): void
    {
        // Render this thing out
        $this->getResponse()->content->set(
            [
                'template' => '@adminImportFolder/index.twig',
                'data' => [
                    'pageCurrent'    => $this->router->generate('adminImportFolder.getIndex'),
                    'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('adminImportFolder.getIndex')),
                    'allNavigation' => $this->navigation->buildNavigation($this->getRouter(), 'adminImportFolder')->getAllNodes(),
                    'templates' => $this->homepageTemplateMapper->findAll()->sortBy('name', 'asc'),
                    'roles' => $this->roleMapper->findAll(),
                    'attachzone' => $this->getAttachZone(),
                ],
            ]
        );
    }


    /**
     * Create folders and render results
     *
     * @api   POST /adminv2/import/folder
     * @since 16.0.0
     * @throws Application\Exception\Exception
     * @throws DefinitionException
     * @throws InvalidArgumentException
     * @throws InvalidColumnException
     * @throws Mapper\Exception
     * @throws PDOException
     * @throws Resource\Exception
     * @throws UnexpectedValueException
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function postImportResults(): void
    {
        $this->extendExecutionTime();

        $post = $this->getRequest()->post;

        if (!\is_array($post->get('attachment'))) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
        }

        $factory = new CollectorFactory();
        $csvFile = $factory->build($post->get('attachment'))->getFirst();

        $bulkHandler = new Bulk_Folder(
            $this->filesystemProxy->readStream($csvFile),
            (int)$post->get('template'),
            $post->get('groupPerm'),
            (bool)$post->get('inherit')
        );

        $performMode = (bool)$post->get('perform');
        $allFolders = $bulkHandler->addFolders($performMode);

        // Render this thing out
        $this->getResponse()->content->set(
            [
                'template' => '@adminImportFolder/importResults.twig',
                'data' => [
                    'pageCurrent'    => $this->router->generate('adminImportFolder.getIndex'),
                    'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('adminImportFolder.getIndex')),
                    'results'        => $allFolders,
                    'perform'        => $performMode,
                ],
            ]
        );
    }


    /**
     * Insert an attach zone into the template
     *
     * @return array
     */
    private function getAttachZone(): array
    {
        $zone = (new Zone('attachment'))
            ->setThumbnailSize(64)
            ->setModalTitle('Select CSV')
            ->setAttachmentsLabel('')
            ->setAttachPrompt('Upload CSV')
            ->setMimeFilter('*/*')
            ->setReadOnly(false)
            ->setMulti(false);

        return $zone->getTwigContext();
    }
}
