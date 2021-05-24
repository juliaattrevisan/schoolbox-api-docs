<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Component_Homepage_SocialStreamController extends Component_Homepage_ControllerAbstract
{
    /**
     * Outputs HTML template defined by config variable 'template'
     *
     * @param String $templateHandle
     * @return bool
     */
    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'container', 'component/homepage/socialstream/')) {
            return false;
        }


        $this->parseConfig();


        $this->setBlock($templateHandle, 'source_folder_deleted', 'source_folder_deleted_block');
        $this->setBlock($templateHandle, 'source_folder_exists', 'source_folder_exists_block');
        $this->setBlock('source_folder_exists', 'socialstream_has_newpost', 'socialstream_has_newpost_block');

        if (!$this->isSourceRenderable()) {
            return $this->renderSourceUnavailable();
        } else {
            $this->setVar('source_folder_deleted_block', '');

            $this->setVar(
                array(
                    'HOMEPAGE_ID' => $this->getSource()->getId(),
                    'SOCIALSTREAM_AJAX_LIMIT' => $this->getConfig('size'),
                )
            );

            /* If you can neither comment nor collaborate, hide the form
             * and disable javascript and hide links
             */
            $newPost = new \SocialStream_Post_String();
            $currentUser = $this->getUser();
            $authenticator = new \SocialStream\Authenticator($newPost, $this->getInstance(), $currentUser);

            if (!$authenticator->canComment() && !$authenticator->canCollaborate()) {
                $this->setVar(
                    array(
                        'socialstream_has_newpost_block' => '',
                    )
                );
            } else {
                if ($authenticator->canCollaborate()) {
                    $this->setVar(
                        array(
                            'FORM_CLASS' => ''
                        )
                    );
                }
                /* Use hidden cloneable
                 */
                else {
                    if ($authenticator->canComment()) {
                        $this->setVar(
                            array(
                                'FORM_CLASS' => 'hide'
                            )
                        );
                    }
                }

                $this->parse('socialstream_has_newpost_block', 'socialstream_has_newpost');
            }

            /* See how many posts there could be at the moment
             */
            $numPosts = \SocialStream\PostMapper::getInstance()->findCount($this->getSource()->getId());

            $this->setVar(
                array(
                    'SOCIALSTREAM_NUM_POSTS' => $numPosts
                )
            );
            $this->parse('source_folder_exists_block', 'source_folder_exists');
        }

        return true;
    }
}
