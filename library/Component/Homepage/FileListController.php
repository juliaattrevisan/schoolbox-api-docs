<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_FileListController extends Component_Homepage_ControllerAbstract
{

    /**
     * @var Group
     */
    private $groupHandler;

    /**
     * @var Schoolbox\Resource\File\Mapper
     */
    private $fileMapper;

    /**
     * @var AllowedFile
     */
    private $allowedFileHandler;

    /**
     * @var Schoolbox\UserMapper
     */
    private $userMapper;

    /**
     * An instance of Category
     *
     * @var \Schoolbox\Resource\Folder\Model
     */
    private $source;

    /**
     * Category Mapper
     *
     * @var \Schoolbox\Resource\Folder\Mapper
     */
    private $categoryMapper = null;

    /**
     * Available filters for this component.
     *
     * @see Component_Config_Filter for how this data is passed in/out of the database
     * @var array
     */
    protected $filterFields = array('document', 'audio', 'video', 'image', 'other');

    /**
     * Available sort fields for this component.
     *
     * @var array
     */
    protected $sortFields = array('name', 'date');

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute()
    {
        $this->groupHandler       = new Group();
        $this->fileMapper         = Schoolbox\Resource\File\Mapper::getInstance();
        $this->userMapper         = Schoolbox\UserMapper::getInstance();
        $this->allowedFileHandler = new AllowedFile();
        $this->setCategoryMapper(Schoolbox\Resource\Folder\Mapper::getInstance());

        $this->source = $this->getContext();

        if (is_numeric($this->getConfig('src'))) {
            try {
                // remove any non-numeric values from the source config
                $this->source = $this->getCategoryMapper()->findById(preg_replace('#[/a-zA-Z]#', '', $this->getConfig('src')));
            } catch (\Exception $e) {
            }
        }
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

        if ($this->permission < READ_ACCESS) {
            return false;
        }

        /* Set up component name
         */
        $this->setComponentName(strlen(trim($this->getConfig('componentname'))) ? $this->getConfig('componentname') : $this->getComponentName());

        $this->setVar(
            array(
                // These are for the Select Alternate Source resourcebrowser
                'COMPONENT_CONFIG_SRC_ID'   => $this->source->getId(),
                'COMPONENT_CONFIG_SRC_NAME' => $this->source->getId() == $this->getContext()->getId()
                        ? 'Local'
                        : $this->source->getName(),
                'FILES_CATEGORY_ID'         => $this->source->getId(),
            )
        );

        $configValue = $this->getConfig('filter');
        $filter      = array();
        for ($i = 0; $i < count($this->filterFields); $i++) {
            $j = $configValue % 2;
            if ($j) {
                $filter[] = $this->filterFields[$i];
            }
            $configValue = (int)$configValue / 2;
        }

        //file output
        $this->setBlock($templateHandle, 'files', 'files_block');
        $this->setBlock('files', 'file_url', 'file_url_block');
        $this->setBlock('files', 'file_no_url', 'file_no_url_block');
        $this->setBlock('files', 'modify_file', 'modify_file_block');

        $fileList = $this->fileMapper->findForFolders(
            [ $this->source->getId() ],
            array(
                'size'      => $this->getConfig('size'),
                'filter'    => $filter,
                'sort'      => $this->getConfig('sort'),
                'sortdir'   => $this->getConfig('sortdir'),
                'getHidden' => $this->permission >= WRITE_ACCESS,
            )
        );

        $this->setVar('files_block', '');

        /** @var Schoolbox\Resource\File\Model[] $fileList */
        if (count($fileList)) {
            $j = 0;
            foreach ($fileList as $file) {
                if ($file->isHidden() && $this->permission < WRITE_ACCESS) {
                    continue;
                }
                // exclude files associated with due work
                // (I strongly doubt resource files are assocd with
                //  due work anymore, and if they are they shouldn't be)
//                if ($file['type'] || $file['typeName']) {
//                    continue;
//                }

                $output = array(
                    'FILE_DESCRIPTION'     => Scrub::text($file->getTitle()),
                    'FILE_ID'              => $file->getId(),
                    'FILE_MIME_IMAGE'      => $file->getStorage()->getMimetype()->getIconFile(),
                    'FILE_POSTED_BY'       => $file->getOwner()->formatName(),
                    'FILE_POSTED_BY_ID'    => $file->getOwner()->getId(),
                    'FILE_POSTED_DATE'     => $file->getCreatedAt()->getShort(),
                    'FILE_POSTED_RELATIVE' => $file->getCreatedAt()->getRelativeTime(),
                    'FILE_MODIFIED_DATE'   => $file->getModifiedAt()->getRelativeTime(),
                    'FILE_COMMENT'         => nl2br(Scrub::text($file->getDescription())),
                    'ON_OFF'               => ++$j % 2 ? '' : 'dark',
                    'HIDDEN'               => $file->isHidden() ? 'translucent' : '',
                );
                $this->setVar($output);

                //set name
                if ($file->getFilename() != "") {
                    $this->setVar('FILE_URL', '/send.php?id=' . $file->getId());
                    $this->setVar('FILE_TARGET', '_blank');

                    $this->setVar('file_no_url_block', '');
                    $this->parse('file_url_block', 'file_url');
                } else {
                    $this->setVar('file_url_block', '');
                    $this->parse('file_no_url_block', 'file_no_url');
                }
                if ($this->permission >= WRITE_ACCESS) {
                    $this->parse('modify_file_block', 'modify_file');
                } else {
                    $this->setVar('modify_file_block', '');
                }

                $this->parse('files_block', 'files', true);

            }
        } else {
            $this->setVar('files_block', '');
        }
        //setup block for submission button
        $this->setVar('FILE_CAT_ID', $this->source->getId());
        $this->setBlock($templateHandle, "submit_file", "submit_file_block");
        if ($this->permission < WRITE_ACCESS) {
            $this->setVar("submit_file_block", "");
        } else {
            $this->parse("submit_file_block", "submit_file");
        }

        /* Need title injected here
         */
        $this->parseConfig();

        return true;
    }

    /* Getters/Setters
     */

    /**
     * Set category mapper
     *
     * @param Schoolbox\Resource\Folder\Mapper $categoryMapper
     */
    private function setCategoryMapper($categoryMapper)
    {
        $this->categoryMapper = $categoryMapper;
    }

    /**
     * Get category mapper
     *
     * @return Schoolbox\Resource\Folder\Mapper
     */
    private function getCategoryMapper()
    {
        return $this->categoryMapper;
    }
}
