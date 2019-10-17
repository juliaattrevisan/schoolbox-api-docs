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
use Schoolbox\Admin\Import\Navigation\SchoolData;
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
     * @param RoleMapper             $roleMapper
     * @param ErrorMapper            $errorMapper
     * @param HomepageTemplateMapper $homepageTemplateMapper
     * @param IConfigLoader          $configLoader
     */
    public function __construct(
        RoleMapper $roleMapper,
        ErrorMapper $errorMapper,
        HomepageTemplateMapper $homepageTemplateMapper,
        IConfigLoader $configLoader
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
                    'allBreadcrumbs' => $this->navigation->buildBreadcrumbs($this->getRouter()),
                    'allNavigation' => $this->navigation->buildNavigation($this->getRouter(), 'adminImportFolder')->getAllNodes(),
                    'templates' => $this->homepageTemplateMapper->findAll()->sortBy('name', 'asc'),
                    'roles' => $this->roleMapper->findAll(),
                    'attachzone' => $this->getAttachZone(),
                ],
            ]
        );
    }


    /** @noinspection PhpUnused */
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
        set_time_limit(600);

        $post = $this->getRequest()->post;

        if (!\is_array($post->get('attachment'))) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
        }

        $factory = new CollectorFactory();
        $csvFile = $factory->build($post->get('attachment'))->getFirst();

        $bulkHandler = new Bulk_Folder(
            $csvFile->readStream(),
            (int)$post->get('template'),
            $post->get('groupPerm'),
            (bool)$post->get('inherit')
        );

        $allFolders = $bulkHandler->addFolders();

        // Render this thing out
        $this->getResponse()->content->set(
            [
                'template' => '@adminImportFolder/importResults.twig',
                'data' => [
                    'allBreadcrumbs' => $this->navigation->buildBreadcrumbs($this->getRouter()),
                    'folders' => $allFolders,
                ],
            ]
        );
    }


    /**
     * Insert an attach zone into the template
     *
     * @return array
     * @throws UnexpectedValueException
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
