<?php
/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Schoolbox\Resource\Attachment;

use Schoolbox\IConfigLoader;
use Schoolbox\L10n\RosettaStone;
use SitePage;

/**
 * Class NavigatorController
 *
 * @package Schoolbox\Resource\Attachment
 */
abstract class NavigatorController extends AbstractController
{

    /**
     * @var string
     */
    protected $template;

    /**
     * @param bool          $multi
     * @param string        $mimeFilter
     * @param IConfigLoader $conf
     * @param RosettaStone  $rosetta
     */
    public function __construct($multi, $mimeFilter = null, $conf = null, $rosetta = null)
    {
        parent::__construct($multi, $mimeFilter);
        $this->template = 'navigator';
    }


    /**
     * @param SitePage $page
     * @return void
     */
    protected function pparse(SitePage $page)
    {

        /* socialstreambrowser has an explicit use case for explicitly being false
         * in browser.js::149 loadNode(). The only way to have folders-only is to
         * specify an empty mimeFilter using (bool)false; null is the default case
         * which shows you both folders and files; otherwise it filters to the string
         * you specified
         *
         * I thought that setting mimeFilter to an empty string would be the equivalent
         * of folders-only, but alright, mr matt will have his mysteries
         */
        if (null === $this->mimeFilter) {
            $mimeFilter = null;
        } elseif (!$this->mimeFilter) {
            $mimeFilter = 'false';
        } else {
            $mimeFilter = "'{$this->mimeFilter}'";
        }

        $page->setVar([
            'MIME_FILTER' => $mimeFilter,
            'SOURCE_SLUG' => substr($this->getSlug(), strpos($this->getSlug(), '/') + 1),
        ]);
    }
}
