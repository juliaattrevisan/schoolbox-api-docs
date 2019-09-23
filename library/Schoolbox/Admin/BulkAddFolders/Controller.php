<?php
/**
 * Bulk add folders admin page
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\BulkAddFolders;

use Alaress\Web\Controller\Base;
use Bulk_Folder;
use InvalidArgumentException;
use Schoolbox\Application;
use Schoolbox\RoleMapper;
use Schoolbox\Resource;
use Schoolbox\Resource\HomepageTemplateMapper;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Error\Mapper as ErrorMapper;
use Scrub;
use UnexpectedValueException;

class Controller extends Base
{
    /**
     * A RoleMapper instance
     *
     * @var RoleMapper
     */
    private $roleMapper;

    /**
     * A HomepageTemplateMapper instance
     *
     * @var HomepageTemplateMapper
     */
    private $homepageTemplateMapper;

    /**
     * @var ErrorMapper
     */
    private $errorMapper;

    /**
     * Get Role Mapper
     *
     * @return RoleMapper
     */
    private function getRoleMapper()
    {
        return $this->roleMapper;
    }

    /**
     * Set Role Mapper
     *
     * @param RoleMapper $roleMapper
     */
    private function setRoleMapper($roleMapper)
    {
        $this->roleMapper = $roleMapper;
    }

    /**
     * Get Error Mapper
     *
     * @return ErrorMapper
     */
    private function getErrorMapper()
    {
        return $this->errorMapper;
    }

    /**
     * Set Error Mapper
     *
     * @param ErrorMapper $errorMapper
     */
    private function setErrorMapper($errorMapper)
    {
        $this->errorMapper = $errorMapper;
    }

    /**
     * Controller constructor.
     */
    public function __construct()
    {
        $this->setRoleMapper(RoleMapper::getInstance());
        $this->setErrorMapper(ErrorMapper::getInstance());
        $this->setHomepageTemplateMapper(HomepageTemplateMapper::getInstance());
    }

    /**
     * Display bulk upload admin page
     */
    public function getIndex()
    {
        // Render this thing out

        $this->getResponse()->content->set(
            array(
                'template' => '@adminBulkAddFolders/index.twig',
                'data'     => array(
                    'pageCurrent' => 'bulk-add-folders',
                    'templates' => $this->getHomepageTemplateMapper()->findAll()->sortBy("name", "asc"),
                    'roles' => $this->getRoleMapper()->findAll(),
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
     * @throws InvalidArgumentException
     * @throws Resource\Exception
     * @throws UnexpectedValueException
     */
    public function postImportResults(): void
    {
        set_time_limit(600);

        if (!is_array($this->getRequest()->post['attachment'])) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
        }
        $factory = new CollectorFactory();
        $csvFile = $factory->build($this->getRequest()->post['attachment'])->getFirst();

        $bulkHandler = new Bulk_Folder($csvFile->readStream(), Scrub::text($this->getRequest()->post['template']));
        $allFolders  = $bulkHandler->addFolders();

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
     * @param Collection|PastoralAttachmentModel $attachments
     * @return array
     */
    private function getAttachZone()
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

    /**
     * Get homepage template mapper
     *
     * @return HomepageTemplateMapper
     */
    private function getHomepageTemplateMapper()
    {
        return $this->homepageTemplateMapper;
    }

    /**
     * Set homepage template mapper
     *
     * @param HomepageTemplateMapper $homepageTemplateMapper
     */
    private function setHomepageTemplateMapper($homepageTemplateMapper)
    {
        $this->homepageTemplateMapper = $homepageTemplateMapper;
    }

}
