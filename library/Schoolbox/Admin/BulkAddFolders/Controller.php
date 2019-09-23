<?php
/**
 * Bulk add folders admin page
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\BulkAddFolders;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate\InvalidColumnException;
use Alaress\Web\Controller\Base;
use Bulk_Folder;
use InvalidArgumentException;
use PDOException;
use Schoolbox\Application;
use Schoolbox\Mapper;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\RoleMapper;
use Schoolbox\Resource;
use Schoolbox\Resource\HomepageTemplateMapper;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Error\Mapper as ErrorMapper;
use UnexpectedValueException;

class Controller extends Base
{
    /** @var RoleMapper */
    private $roleMapper;

    /** @var HomepageTemplateMapper */
    private $homepageTemplateMapper;

    /** @var ErrorMapper */
    private $errorMapper;

    /**
     * @param RoleMapper             $roleMapper
     * @param ErrorMapper            $errorMapper
     * @param HomepageTemplateMapper $homepageTemplateMapper
     */
    public function __construct(
        RoleMapper $roleMapper,
        ErrorMapper $errorMapper,
        HomepageTemplateMapper $homepageTemplateMapper
    )
    {
        $this->roleMapper             = $roleMapper;
        $this->errorMapper            = $errorMapper;
        $this->homepageTemplateMapper = $homepageTemplateMapper;
    }

    /**
     * Display bulk upload admin page
     *
     * @api   GET /adminv2/bulk-add-folders
     * @since 16.0.0
     * @throws UnexpectedValueException
     */
    public function getIndex(): void
    {
        // Render this thing out
        $this->getResponse()->content->set(
            array(
                'template' => '@adminBulkAddFolders/index.twig',
                'data'     => array(
                    'pageCurrent' => 'bulk-add-folders',
                    'templates' => $this->homepageTemplateMapper->findAll()->sortBy("name", "asc"),
                    'roles' => $this->roleMapper->findAll(),
                    'attachzone' => $this->getAttachZone(),
                ),
            )
        );
    }


    /** @noinspection PhpUnused */
    /**
     * Create folders and render results
     *
     * @api   POST /adminv2/bulk-add-folders
     * @since 16.0.0
     * @throws Application\Exception\Exception
     * @throws DefinitionException
     * @throws InvalidArgumentException
     * @throws InvalidColumnException
     * @throws Mapper\Exception
     * @throws PDOException
     * @throws Resource\Exception
     * @throws UnexpectedValueException
     */
    public function postImportResults(): void
    {
        set_time_limit(600);

        $post = $this->getRequest()->post;

        if (!is_array($post->get('attachment'))) {
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
                'template' => '@adminBulkAddFolders/importResults.twig',
                'data'     => [
                    'pageCurrent' => 'bulk-add-folders',
                    'folders'     => $allFolders,
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
        $zone = new Zone('attachment');
        $zone
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
