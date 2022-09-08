<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Attachment;

use SitePage;

abstract class AbstractController implements ControllerInterface
{
    /** @var bool */
    protected $multi;

    /** @var string */
    protected $mimeFilter;

    /** @var string */
    protected $slug;

    /**
     * @var bool
     */
    protected $useFolder = true;

    /**
     * @var int
     */
    protected $defaultFolderId = 0;

    /**
     * @param bool   $multi
     * @param string $mimeFilter
     * @param bool   $useFolder
     * @param int    $defaultFolderId
     */
    public function __construct($multi, $mimeFilter = null, $useFolder = true, $defaultFolderId = 0)
    {
        $regex = '`([A-Za-z0-9_]+\\\\[A-Za-z0-9_]+)Controller$`';
        $class = get_class($this);

        preg_match($regex, $class, $matches);

        $this->multi      = $multi;
        $this->mimeFilter = $mimeFilter;
        $this->slug       = strtolower(str_replace('\\', '/', $matches[1]));
        $this->template   = $this->slug;
        $this->useFolder  = $useFolder;
        $this->defaultFolderId = $defaultFolderId;
    }


    /**
     * @return string
     */
    public function getUrl()
    {
        /* SC: Finally I found this place
         * AS: Finally I found this place (or its child implementations) too
         */
        $prefix = sprintf('/ajax/resources/%s/%s', $this->getSlug(), $this->multi ? 'multi' : 'single');

        $args = [];

        /* mimeFilter not set; use default behaviour (folders and files)
         */
        if (null === $this->mimeFilter) {
            // noop
        } elseif (false === $this->mimeFilter) {
            /* mimeFilter was false; just show folders
             */
            $args['mime'] = '';
        } else {
            /* mimeFilter was set to some string so urlencode it then supply it
             */
            $args['mime'] = urlencode($this->mimeFilter);
        }

        /* If we should have a Use This Folder button
         */
        if ($this->useFolder) {
            $args['f'] = true;
        }

        /* ID of the default folder to start browsing resources from
        */
        if ($this->defaultFolderId) {
            $args['fid'] = $this->defaultFolderId;
        }

        return $prefix . '?' . http_build_query($args);
    }


    /**
     * @return string
     */
    public function getSlug()
    {
        return $this->slug;
    }


    /**
     * @param SitePage $page
     * @return void
     */
    public function parse(SitePage $page)
    {
        $page->setAjax();
        $page->setFile(TEMPLATE_content, "/resources/{$this->template}.html");
        $page->injectGlobalVars();

        $this->pparse($page);

        $page->template->pparse('finaloutput', TEMPLATE_content);
    }


    /**
     * @param SitePage $page
     * @return void
     */
    abstract protected function pparse(SitePage $page);
}
