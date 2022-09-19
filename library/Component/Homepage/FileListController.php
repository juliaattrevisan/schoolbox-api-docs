<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;

class Component_Homepage_FileListController extends Component_Homepage_ControllerAbstract
{

    /**
     * @var Schoolbox\Resource\File\Mapper
     */
    private $fileMapper;


    /**
     * Available filters for this component.
     *
     * @see Component_Config_Filter for how this data is passed in/out of the database
     * @var string[]
     */
    protected $filterFields = \Schoolbox\Resource\File\Model::FILTER_FIELDS;

    /**
     * Available sort fields for this component.
     *
     * @var string[]
     */
    protected $sortFields = ['name', 'date'];

    /**
     * Component_Homepage_FileListController constructor.
     * @param Component_Instance $instance
     * @param User               $user
     * @param SitePage           $sitepage
     */
    public function __construct(Component_Instance $instance, User $user, SitePage $sitepage)
    {
        parent::__construct($instance, $user, $sitepage);

        $this->fileMapper = Schoolbox\Resource\File\Mapper::getInstance();
    }

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute()
    {
        // If current user can't view component's source, only display the
        // component if the user has context WRITE+ permission
        if (!$this->isSourceRenderable()) {
            return $this->hasContextWriteAccess();
        }

        return true;
    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     *
     * @param String $templateHandle
     *
     * @return boolean
     */
    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'fileList')) {
            return false;
        }
        if (!$this->isSourceRenderable()) {
            return $this->renderSourceUnavailableTwig($templateHandle);
        }

        /* Need title injected here
         */
        $this->parseConfig();

        $this->setBlock($templateHandle, 'has_file', 'has_file_block');
        $this->setBlock($templateHandle, 'empty_state', 'empty_state_block');

        $filter = Component_Config_Filter::getSelectedFilters((int)$this->getConfig('filter'), $this->filterFields);

        $fileList = $this->fileMapper->findForFolders(
            [$this->getSource()->getId()],
            [
                'size'      => $this->getConfig('size'),
                'filter'    => $filter,
                'sort'      => $this->getConfig('sort'),
                'sortdir'   => $this->getConfig('sortdir'),
                'getHidden' => $this->getContentPermission() >= UserAccess::ACCESS_WRITE,
            ]
        );

        //setup block for submission button
        $this->setVar('FILE_CAT_ID', $this->getSource()->getId());

        /** @var Schoolbox\Resource\File\Model[] $fileList */
        if (count($fileList) === 0) {
            if ($this->getContentPermission() < UserAccess::ACCESS_WRITE) {
                return false;
            }
            $this->setVar('has_file_block', '');
            $this->parse('empty_state_block', 'empty_state');
            $this->setVar('files_block', '');
        } else {
            /* Set up component name
             */
            $this->setComponentName(strlen(trim($this->getConfig('componentname'))) ? $this->getConfig('componentname') : $this->getComponentName());

            $this->setBlock('has_file', 'source_folder_deleted', 'source_folder_deleted_block');
            $this->setBlock('has_file', 'source_folder_exists', 'source_folder_exists_block');
            $this->setBlock('source_folder_exists', 'files', 'files_block');
            $this->setBlock('source_folder_exists', "component_action", "component_action_block");
            $this->setBlock('files', 'file_url', 'file_url_block');
            $this->setBlock('files', 'file_no_url', 'file_no_url_block');
            $this->setBlock('files', 'modify_file', 'modify_file_block');
            $this->setBlock('files', 'file_modify_user', 'file_modify_user_block');
            $this->setBlock('files', 'file_deleted_user', 'file_deleted_user_block');

            $this->setVar('empty_state_block', '');

            $this->setVar(
                [
                    // These are for the Select Alternate Source resourcebrowser
                    'COMPONENT_CONFIG_SRC_ID'   => $this->getSource()->getId(),
                    'COMPONENT_CONFIG_SRC_NAME' => $this->getSource()->getId() == $this->getContext()->getId()
                        ? 'Local'
                        : Scrub::text($this->getSource()->getName()),
                    'FILES_CATEGORY_ID'         => $this->getSource()->getId(),
                ]
            );


            $this->setVar(
                [
                    'source_folder_deleted_block' => '',
                    'files_block'                 => '',
                ]
            );

            $folderId = $this->getContext()->getId();
            foreach ($fileList as $file) {
                if ($file->isHidden() && $this->getContentPermission() < UserAccess::ACCESS_WRITE) {
                    continue;
                }

                $mostRecentEditor = $file->getModifiedBy() ?: $file->getOwner();
                $message          = $file->getModifiedBy() ? 'Last updated' : 'Posted';
                $mostRecentDate   = $file->getModifiedAt() ?: $file->getCreatedAt();

                $this->setVar(
                    [
                        'FILE_DESCRIPTION'     => Scrub::text($file->getTitle()),
                        'FILE_ID'              => $file->getId(),
                        'FILE_MIME_IMAGE'      => $file->getStorage()->getMimetype()->getIconFile(),
                        'FILE_MIME_TYPE'       => $file->getStorage()->getMimetype()->getDescription(),
                        'FILE_POSTED_BY'       => $mostRecentEditor->getFullName(),
                        'FILE_POSTED_BY_ID'    => $mostRecentEditor->getId(),
                        'FILE_POSTED_DATE'     => $mostRecentDate->getShort(),
                        'FILE_POSTED_RELATIVE' => $mostRecentDate->getRelativeTime(),
                        'FILE_POSTED_MESSAGE'  => $message,
                        'FILE_COMMENT'         => nl2br(Scrub::text($file->getDescription())),
                        'HIDDEN'               => $file->isHidden() ? 'translucent' : '',
                        'RETURN_URL'           => Redirect::RETURN_URL . '=' . Redirect::encode("/homepage/$folderId"),
                    ]
                );

                if (!$this->getConfig('compactMode')) {
                    $this->setVar('FILE_COMPACT', '');
                } else {
                    $this->setVar('FILE_COMPACT', 'compact');
                }

                $this->setVar('file_modify_user_block', '');
                $this->setVar('file_deleted_user_block', '');

                if (!$mostRecentEditor->isDeleted()) {
                    $this->parse('file_modify_user_block', 'file_modify_user');
                } else {
                    $this->parse('file_deleted_user_block', 'file_deleted_user');
                }

                //set name
                if ($file->getFilename() != "") {
                    //if image can be converted, lets send the convertable image, else, just return the file
                    $fileUrl = '/send.php?id=' . $file->getId();
                    if ($file->getMimetype()->isImage() && $file->getMimetype()->isTranscodable()) {
                        $fileUrl = "/storage/image.php?hash={$file->getHash()}";
                    }
                    $this->setVar(
                        [
                            'FILE_URL'          => $fileUrl,
                            'FILE_TARGET'       => '_blank',
                            'file_no_url_block' => '',
                        ]
                    );
                    $this->parse('file_url_block', 'file_url');
                } else {
                    $this->setVar('file_url_block', '');
                    $this->parse('file_no_url_block', 'file_no_url');
                }
                if ($this->getContentPermission() >= UserAccess::ACCESS_WRITE) {
                    $this->parse('modify_file_block', 'modify_file');
                } else {
                    $this->setVar('modify_file_block', '');
                }

                $this->parse('files_block', 'files', true);
            }

            if ($this->getContentPermission() < UserAccess::ACCESS_WRITE) {
                $this->setVar([
                                  'component_action_block' => '',
                              ]);
            } else {
                $this->parse("component_action_block", "component_action");
            }

            $this->parse('source_folder_exists_block', 'source_folder_exists');
            $this->parse('has_file_block', 'has_file');
        }

        return true;
    }
}
