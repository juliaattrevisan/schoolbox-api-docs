<?php
/**
 * Download
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Crocodoc\Renderer\Strategy;

use Alaress_Byte_Format;
use Plagscan_Document;
use Plagscan_DocumentMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Submission;
use Schoolbox\Mapper;
use Schoolbox\User;

class Download extends Base
{

    /**
     * The template file to inject
     *
     * @var string
     */
    protected $templateFile = 'submission/inlineGrading/injectables/views/download.html';

    /**
     * Control file, to determine how to return work
     *
     * @var string
     */
    protected $controlFile = 'submission/inlineGrading/injectables/controls/redirect-return-control.html';

    /**
     * Render injectable
     *
     * @return mixed|void
     */
    public function render()
    {
        $this->getPage()->setFile('injectable', $this->getTemplateFile());
        $this->getPage()->setBlock('injectable', 'plag_score', 'plag_score_block');
        $this->plag();

        $work = $this->getSubmission();

		try {
			$plagMapper = Plagscan_DocumentMapper::getInstance();
			$plagDocument = $plagMapper->findOneByColumn('file_id', $work->getId());

            $plagHref = sprintf(
                "%s%s/submission/plagiarismReport/%s/?zx_h=600px",
                $this->getPage()->conType(), $_SERVER['HTTP_HOST'], $plagDocument->getPlagscanId()
            );
        } catch (\Exception $e) {
            $plagHref = '';
        }

        $filename = $work->getFilename();
        $filesize = $work->getSize();
        $mimetype = $work->getMimetype();

		$this->getPage()->setVar(array(
			'MOST_RECENT_SUBMISSION_FILE_ID'        => $work->getId(),
			'MOST_RECENT_SUBMISSION_FILE_NAME'      => $filename,
			'MOST_RECENT_SUBMISSION_FILE_SIZE'      => Alaress_Byte_Format::bytesToString($filesize),
			'MOST_RECENT_SUBMISSION_FILE_THUMB'     => $mimetype->getIconFile(),
			'MOST_RECENT_SUBMISSION_FILE_DATE'      => $work->getCreatedAt()->getRelativeTime(),
			'MOST_RECENT_SUBMISSION_FILE_PLAG_HREF' => base64_encode($plagHref)
		));

        $this->getPage()->parse('INJECTED_INTERFACE_TEMPLATE', 'injectable');

        /* Upload File
         */
        $this->parseInjectableUpload();

        /* Control how Return Work is done
         */
        $this->getPage()->setVar(
            array(
                'INJECTED_INTERFACE_CONTROL' => ''
            )
        );
    }

	/**
	 * Plag it
	 */
	private function plag()
    {
        /** @var User $sessionUser */
        $sessionUser = $_SESSION['user'];

		$isStaff = $sessionUser->getRole()->isStaff();
		$isOwner = $this->getSubmission()->getOwner()->getId() == $sessionUser->getId();

		if ($isStaff || ($isOwner && \Config::get('plagscan_student_visible'))) {

			// retrieve plagiarism score, if available
			$docMapper = Plagscan_DocumentMapper::getInstance();
			$plagDoc = null;

            try {
                /* @var $plagDoc Plagscan_Document */
                $plagDoc = $docMapper->findOneByColumn('file_id', $this->getSubmission()->getId());

                if ($plagDoc->getId() && $plagDoc->getStatus() == \Plagscan_Document::STATUS_COMPLETE) {

                    // what colour should the plagiarism text be, and what should the tooltip say?
                    if ($plagDoc->getScore() > 60) {
                        $status = 'critical';
                        $tooltip = 'Your submission is extremely similar to other documents scanned.';
                    } elseif ($plagDoc->getScore() > 20) {
                        $status = 'warn';
                        $tooltip = 'Your submission is somewhat similar to other documents scanned.';
                    } else {
                        $status = 'ok';
                        $tooltip = 'Your submission is quite different to other documents scanned.';
                    }

					$this->getPage()->setVar(array(
						'MOST_RECENT_SUBMISSION_FILE_PLAG_CLASS' => $status,
						'MOST_RECENT_SUBMISSION_FILE_PLAGSCORE' => sprintf("%d%%", $plagDoc->getScore()),
						'MOST_RECENT_SUBMISSION_FILE_PLAG_TITLE' => $tooltip,
					));
					$this->getPage()->parse('plag_score_block', 'plag_score');
				}
				else {
					$this->getPage()->setVar('plag_score_block', '');
				}
			}
			catch (Mapper\Exception $e) {
				$this->getPage()->setVar('plag_score_block', '');
			}
		}
		else {
			$this->getPage()->setVar('plag_score_block', '');
		}
	}
}