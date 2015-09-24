<?php
/**
 * An attachment controller for user media (audio/video recordings).
 *
 * @see https://w3c.github.io/mediacapture-main/getusermedia.html
 *
 * @author      Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright   2015 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

namespace Schoolbox\Resource\Attachment\Internal;


use Schoolbox\Resource\Attachment\AbstractController;
use SitePage;

class UsermediaController extends AbstractController implements InternalInterface {

    /**
     * @param SitePage $page
     */
    protected function pparse(SitePage $page)
    {
        if (is_null($this->mimeFilter) || $this->mimeFilter == '*/*') {
            $mime = 'null';
        } else {
            $mime = "'{$this->mimeFilter}'";
        }

        $page->setVar(
            array(
                'MAX_FILES'      => $this->multi ? '32' : '1',
                'ACCEPTED_FILES' => $mime
            )
        );
    }


    /**
     * @return string
     */
    public function getLabel()
    {
        return 'Recording';
    }


    /**
     * @return string
     */
    public function getRenderMethod()
    {
        return self::RENDER_INLINE;
    }

    /**
     * @return string
     */
    public function getIcon()
    {
        return 'mic';
    }

}