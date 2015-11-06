<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_MembersController extends Component_Homepage_ControllerAbstract
{

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute()
    {
    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'members')) {
            return false;
        }
        $this->parseConfig();

        //output users of group
        $this->setBlock($templateHandle, "member_email", "member_email_block");
        $this->setBlock($templateHandle, "person", "person_block");
        $this->setBlock('person', 'has_thumbnail', 'has_thumbnail_block');
        $this->setBlock('person', 'has_icon', 'has_icon_block');

        $this->setVar('has_thumbnail_block', '');
        $this->setVar('has_icon_block', '');
        $this->setVar('person_block', '');

        /* Display options
         */
        $displayOptions = array(
            'grid' => array(
                'name'  => 'Grid',
                'value' => 'grid',
                'css'   => 'grid',
            ),
            'list' => array(
                'name'  => 'List',
                'value' => 'list',
                'css'   => '',
            ),
        );

        $display = $this->getConfig('displayPortraits') ? $this->getConfig('displayPortraits') : 'grid';
        $this->setVar(
            array(
                'LIST_STYLE' => $displayOptions[$display]['css'],
            )
        );

        $userHandler = new User;
        $userList    = $userHandler->getAllInCategory($this->getContext()->getId());
        $emailObj    = new Email();

        $emailall = array();
        if (is_array($userList)) {
            $j = 0;
            foreach ($userList as $user) {
                $name  = $userHandler->formatName($user);
                $email = $emailObj->formatEmailFromArray($user);
                $meta  = $userHandler->getByID($user['uid']);

                if ($user['type'] == 3) {
                    $name .= " (Group Admin.)";
                }

                if ($email) {
                    $emailall[] = $email;
                }

                $this->setVar(
                    array(
                        'ON_OFF'             => ++$j % 2 ? '' : 'dark',
                        'PERSON_UID'         => $user['uid'],
                        'PERSON_NAME'        => $name,
                        'PERSON_EXTERNAL_ID' => $user['synergy_id'],
                        'PERSON_GROUP'       => Scrub::text($meta['gname']),
                        'LIST_STYLE'         => $displayOptions[$display]['css'],
                    )
                );

                /* Don't display images in list view
			     */
                if ($this->getConfig('displayPortraits') == 'grid') {
                    $this->setVar(array(
                        'GRID_VIEW_CLASSES' => 'medium-block-grid-2'
                    ));

                    $this->parse('has_thumbnail_block', 'has_thumbnail');
                    $this->setVar('has_icon_block', '');
                } else {
                    $this->setVar(array(
                        'GRID_VIEW_CLASSES' => ''
                    ));

                    $this->setVar('has_thumbnail_block', '');
                    $this->parse('has_icon_block', 'has_icon');
                }

                $this->parse('person_block', 'person', true);

                /* Clear children after parsing out the parent
			     */
                $this->setVar('has_thumbnail_block', '');
                $this->setVar('has_icon_block', '');
            }

            $this->setVar("REPLYTO", $emailObj->formatEmailFromArray($_SESSION));
            $this->setVar("EMAILALL", implode(',', $emailall));

            if (\Cfg::get('mail_enable')) {
                $this->parse("member_email_block", "member_email");
            } else {
                $this->setVar(
                    array(
                        'member_email_block' => ''
                    )
                );
            }
        } else {
            $this->setVar('member_email_block', '');
        }

        return true; // everything went according to plan
    }
}
