<?php
/**
 * Bulk add folders admin page
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Admin\BulkAddFolders;

use Alaress\Web\Controller\Base;
use Bulk_Category;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Schoolbox\Resource\Storage;
use Schoolbox\Resource\HomepageTemplateMapper;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Error\Mapper as ErrorMapper;
use Scrub;

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

    /**
     * Create folders and render results
     */
    public function postImportResults()
    {
        set_time_limit(600);

        if (!$this->getUser()->isSuperuser()) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
        }

        $allFolders = null;
        if (is_array($this->getRequest()->post['attachment'])) {
            $factory = new CollectorFactory();
            $csvFile = $factory->build($this->getRequest()->post['attachment'])->getFirst();

            $bulkHandler = new Bulk_Category(
                $csvFile->readStream(),
                Scrub::text($this->getRequest()->post['template'])
            );
            $allFolders = $bulkHandler->addCategories();
        } else {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
        }

        // Render this thing out

        $this->getResponse()->content->set(
            array(
                'template' => '@adminBulkAddFolders/importResults.twig',
                'data'     => array(
                    'pageCurrent' => 'bulk-add-folders',
                    'folders' => $allFolders
                ),
            )
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
