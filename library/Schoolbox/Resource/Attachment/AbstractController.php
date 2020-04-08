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
     * @param bool   $multi
     * @param string $mimeFilter
     */
    public function __construct($multi, $mimeFilter = null)
    {
        $regex = '`([A-Za-z0-9_]+\\\\[A-Za-z0-9_]+)Controller$`';
        $class = get_class($this);

        preg_match($regex, $class, $matches);

        $this->multi      = $multi;
        $this->mimeFilter = $mimeFilter;
        $this->slug       = strtolower(str_replace('\\', '/', $matches[1]));
        $this->template   = $this->slug;
    }


    /**
     * @return string
     */
    public function getUrl()
    {
        /* Finally I found this place
         */
        $prefix = sprintf('/ajax/resources/%s/%s', $this->getSlug(), $this->multi ? 'multi' : 'single');

        /* mimeFilter not set; use default behaviour (folders and files)
         */
        if (null === $this->mimeFilter) {
            return $prefix;
        }

        /* mimeFilter was false; just show folders
         */
        if (false === $this->mimeFilter) {
            return "{$prefix}?mime=";
        }

        /* mimeFilter was set to some string so urlencode it then supply it
         */
        return "{$prefix}?mime=" . urlencode($this->mimeFilter);
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

        $this->pparse($page);

        $page->template->pparse('finaloutput', TEMPLATE_content);
    }


    /**
     * @param SitePage $page
     * @return void
     */
    protected abstract function pparse(SitePage $page);

}
