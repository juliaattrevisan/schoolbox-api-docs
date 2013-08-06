<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_DropBoxController extends Component_Homepage_ControllerAbstract {

    /**
     * @var AllowedFile
     */
    private $allowedFileHandler;

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute() {
        $this->allowedFileHandler = new AllowedFile();
    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle) {
        if(!parent::loadTemplate($templateHandle, 'dropBox')) {
            return false;
        }
        $this->parseConfig();


        //output drop box
        $this->setBlock($templateHandle, "drop_student", "drop_student_block");
        $this->setBlock('drop_student', "drop_success", "drop_success_block");
        $this->setBlock($templateHandle, "drop_staff", "drop_staff_block");
        $this->setBlock('drop_staff', 'drop_file', 'drop_file_block');

        if ($this->permission < WRITE_ACCESS) {

            $this->setVar('drop_success_block', '');
            if ($_SESSION['dropSuccess']) {
                $this->parse('drop_success_block', 'drop_success', true);
                unset($_SESSION['dropSuccess']);
            }

            $this->parse('drop_student_block', 'drop_student');
            $this->setVar('drop_staff_block', '');
        }
        else {

            $this->setVar('drop_student_block', '');
            $dropbox = new Dropbox;
            $fileList = $dropbox->getFileList($this->getContext('id'));
            $userHandler = new User();

            if(is_array($fileList)) {
                $j = 0;
                foreach($fileList as $file) {
                    $date = new Alaress_Date_Format($file['subdate'], 'y-m-d h:i:s');

                    $output = array('DROPBOX_MIME_IMAGE'	=> $this->allowedFileHandler->getClass($file["iconFile"]),
                        'DROPBOX_DESCRIPTION' => Scrub::text($file['description']),
                        'DROPBOX_ID'		=> $file['id'],
                        'DROP_SUBMITTED_BY' => "<a href=\"/search/user/{$file['uid']}\">".$userHandler->formatName($file).'</a>',
                        'DROP_SUBMITTED_TIME'=> $date->getShort(),
                        'ON_OFF' => ++$j % 2 ? '' : 'dark',
                    );
                    $this->setVar($output);
                    $this->parse("drop_file_block", "drop_file", true);
                }
            }
            else
                $this->setVar("drop_file_block", "");

            $this->parse("drop_staff_block", "drop_staff");

        }

        return true;
    }

}
