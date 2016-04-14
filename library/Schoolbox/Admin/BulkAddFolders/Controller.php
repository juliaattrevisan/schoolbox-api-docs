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
            $this->getErrorMapper()->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
        }

        $allFolders = null;

        if (isset($this->getRequest()->files['bulkadd']['tmp_name']) && $this->getRequest()->files['bulkadd']['tmp_name']) {
            $bulkHandler = new Bulk_Category(
                $this->getRequest()->files['bulkadd']['tmp_name'],
                Scrub::text($this->getRequest()->post['template'])
            );
            $allFolders = $bulkHandler->addCategories();
        } else {
            $this->getErrorMapper()->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
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
