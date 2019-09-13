<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Folder\Search;

use Alaress\Web\Controller\Base as BaseController;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Folder\Mapper;
use function in_array;

class Controller extends BaseController
{
    /** @var Mapper */
    private $folderMapper;

    /** @var Access */
    private $access;


    /**
     * @param Mapper $folderMapper
     * @param Access $access
     */
    public function __construct(
        Mapper $folderMapper,
        Access $access
    ) {
        $this->folderMapper = $folderMapper;
        $this->access       = $access;
    }


    /**
     * Return a JSON list of folders which match the given query.
     *
     * Accepts these query string parameters:
     * * query: a search term
     * * types: a list of types of folder which this request may fetch
     *
     * @api   GET /resource/folder/search
     * @since 19.1.0
     */
    public function getFolderSearch(): void
    {
        $userClassIds = array_map('intval', array_column($this->getSession()->get('subjects') ?? [], 'id'));
        $userGroupIds = $this->folderMapper->findForUser(
            $this->getUser(),
            false,
            UserAccess::ACCESS_READ,
            false,
            true
        )->getPropertyValues('id');

        $response = [];
        foreach ($this->folderMapper->searchByName($this->request->query->get('query')) as $folder) {
            if ($this->access->getFolderAccess($this->getUser(), $folder->getId()) < UserAccess::ACCESS_READ) {
                continue;
            }

            $optgroup   = null;
            $breadcrumb = null;
            if ($folder->isClass()) {
                // My class, or someone else's?
                $optgroup = in_array($folder->getId(), $userClassIds, true) ? 'class' : 'otherClass';
            } elseif ($folder->isGroup()) {
                // My group, or someone else's?
                $optgroup = in_array($folder->getId(), $userGroupIds, true) ? 'group' : 'otherGroup';
            } else {
                // Show breadcrumbs for other folders,
                // because many folders could have the same or similar names
                $optgroup   = 'otherFolder';
                $breadcrumb = $this->folderMapper->breadcrumb($folder)->map(static function (Folder\Model $v) {
                    return [
                        'id'   => $v->getId(),
                        'name' => $v->getNameWithCodes(),
                    ];
                });
            }

            $response[] = [
                'id'         => $folder->getId(),
                'name'       => $folder->getNameWithCodes(),
                'optgroup'   => $optgroup,
                'breadcrumb' => $breadcrumb,
            ];
        }

        $this->response->content->set($this->buildJsonOutput($response));
    }
}
