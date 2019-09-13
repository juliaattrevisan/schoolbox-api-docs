<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
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
    private const FOLDER_TYPE_MY_CLASS     = 'class';
    private const FOLDER_TYPE_MY_GROUP     = 'group';
    private const FOLDER_TYPE_OTHER_CLASS  = 'otherClass';
    private const FOLDER_TYPE_OTHER_GROUP  = 'otherGroup';
    private const FOLDER_TYPE_OTHER_FOLDER = 'otherFolder';


    /**
     * @var Mapper
     */
    private $folderMapper;


    /**
     * @var Access
     */
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
        $types    = (array)$this->request->query->get('types', []);
        foreach ($this->folderMapper->searchByName($this->request->query->get('query')) as $folder) {
            if ($this->access->getFolderAccess($this->getUser(), $folder->getId()) < UserAccess::ACCESS_READ) {
                continue;
            }

            // Determine option group this folder belongs in
            $optgroup = null;
            if ($folder->isClass()) {
                // My class, or someone else's?
                $optgroup = in_array($folder->getId(), $userClassIds, true)
                    ? self::FOLDER_TYPE_MY_CLASS
                    : self::FOLDER_TYPE_OTHER_CLASS;
            } elseif ($folder->isGroup()) {
                // My group, or someone else's?
                $optgroup = in_array($folder->getId(), $userGroupIds, true)
                    ? self::FOLDER_TYPE_MY_GROUP
                    : self::FOLDER_TYPE_OTHER_GROUP;
            } else {
                $optgroup = self::FOLDER_TYPE_OTHER_FOLDER;
            }

            // Don't return folder if it's not in the list of types I asked for
            if (!empty($types) && !in_array($optgroup, $types, true)) {
                continue;
            }

            // Show breadcrumbs for other folders,
            // because many folders could have the same or similar names
            $breadcrumb = null;
            if ($optgroup === self::FOLDER_TYPE_OTHER_FOLDER) {
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
