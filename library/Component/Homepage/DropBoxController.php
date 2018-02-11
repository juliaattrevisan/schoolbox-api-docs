<?php
/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Schoolbox\Collection;
use Schoolbox\Permission\Access\UserAccess;

class Component_Homepage_DropBoxController extends Component_Homepage_ControllerAbstract
{

    /**
     * Renders the data into the SitePage. Presentation logic.
     *
     * @param String $templateHandle
     *
     * @return boolean
     */
    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'dropBox')) {
            return false;
        }
        
        $this->parseConfig();

        // Output drop box
        
        $this->setBlock($templateHandle, "drop_student", "drop_student_block");
        $this->setBlock('drop_student', "drop_success", "drop_success_block");
        $this->setBlock($templateHandle, "drop_staff", "drop_staff_block");
        $this->setBlock($templateHandle, 'empty_state', 'empty_state_block');
        $this->setBlock('drop_staff', 'drop_file', 'drop_file_block');

        if ($this->getContentPermission() < UserAccess::ACCESS_WRITE) {
            $this->setVar('drop_success_block', '');
            $this->setVar('empty_state_block', '');

            if ($_SESSION['dropSuccess']) {
                $this->parse('drop_success_block', 'drop_success', true);
                unset($_SESSION['dropSuccess']);
            }

            $zone = new \Schoolbox\Resource\Attachment\Zone('dropzbox');
            $zone->setMulti(false)
                 ->setThumbnailSize(64)
                 ->setAttachPrompt('Add a file')
                 ->setModalTitle('Select file')
                 ->setAttachmentsLabel('')
                 ->renderIntoSitePage($this->getSitePage(), 'ATTACH_ZONE');

            $this->parse('drop_student_block', 'drop_student');
            $this->setVar('drop_staff_block', '');
        } else {
            $this->setVar('drop_student_block', '');

            /** @var Collection|Schoolbox\Dropbox\Model[] $fileList */
            $fileList = Schoolbox\Dropbox\Mapper::getInstance()->findByColumn('category', $this->getContext()->getId());

            if ($fileList->count()) {
                $this->setVar('empty_state_block', '');

                $j = 0;
                foreach ($fileList as $file) {
                    if (!$file->getOwner() instanceof \Schoolbox\User) {
                        $link = "Unknown User";
                    } else {
                        $link = "<a href='/search/user/{$file->getOwner()->getId()}'>{$file->getOwner()->formatName()}</a>";
                    }

                    $output = [
                        'DROPBOX_MIME_IMAGE'  => $file->getStorage()->getMimetype()->getIconFile(),
                        'DROPBOX_DESCRIPTION' => Scrub::text($file->getTitle()),
                        'DROPBOX_ID'          => $file->getId(),
                        'DROP_SUBMITTED_BY'   => $link,
                        'DROP_SUBMITTED_TIME' => $file->getCreatedAt()->getShort(),
                        'ON_OFF'              => ++$j % 2 ? '' : 'dark',
                    ];
                    $this->setVar($output);
                    $this->parse("drop_file_block", "drop_file", true);
                }
            } else {
                $this->setVar("drop_file_block", "");
                $this->parse('empty_state_block', 'empty_state');
            }

            $this->parse("drop_staff_block", "drop_staff");
        }

        return true;
    }
}

