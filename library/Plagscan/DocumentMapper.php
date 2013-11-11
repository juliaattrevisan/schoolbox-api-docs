<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */
use Schoolbox\Assessment\Component\SubmissionBox\Submission;
use Schoolbox\Assessment\Component\SubmissionBox\SubmissionMapper;

class Plagscan_DocumentMapper extends Schoolbox\Mapper {

	/**
	 * @var Plagscan_Gateway
	 */
	private $gateway;

	protected function setup() {
		parent::setup();
		$plagscanApiKey = Config::get('plagscan_api_key');
		$plagscanUsername = Config::get('plagscan_username');
		$this->gateway = new Plagscan_Gateway($plagscanUsername, $plagscanApiKey);
	}

	protected function mapping () {
		$this->hasTable('plagscan_document');
		$this->hasModel('Plagscan_Document');
		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true
		));

		$this->hasMap(array(
			'column'     => 'file_id',
			'property'   => 'fileId',
			'type'       => self::TYPE_INTEGER,
		));

		$this->hasMap(array(
			'column'     => 'plagscan_id',
			'property'   => 'plagscanId',
			'type'       => self::TYPE_INTEGER,
		));

		$this->hasMap(array(
			'column'     => 'status',
			'property'   => 'status',
			'type'       => self::TYPE_INTEGER,
		));

		$this->hasMap(array(
			'column'     => 'score',
			'property'   => 'score',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'html',
			'property'   => 'html',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'deleted',
			'property'   => 'deleted',
			'type'       => self::TYPE_DATETIME,
		));

		$this->hasSoftDeleteDateField('deleted');
	}

	/**
	 * Saves a Plagscan document.
	 *
	 * @param Plagscan_Document $dirtyDocument
	 *
	 * @return array
	 */
	public function save ($dirtyDocument) {
		$existing_id = null;
		try {
			// Don't do anything if $existing_document exists already
			$existing_document = $this->findById($dirtyDocument->getId());
			$existing_id       = $existing_document->getId();
		}
		catch (Schoolbox\Mapper\Exception $e) {}
		if (!isset($existing_id)) {
			$this->clearPreviousVersions($dirtyDocument);
			$file = $dirtyDocument->getFile();
			$documentId = $this->gateway->submit($file['name'], $file['file']);

			if (!empty($documentId)) {
				$this->gateway->check($documentId);
				$dirtyDocument->setPlagscanId($documentId);
				$dirtyDocument->setStatus(Plagscan_Document::STATUS_QUEUED);
			}
			else {
				return false;
			}
		}
		return parent::save($dirtyDocument);
	}

    /**
     * Deletes all plagscan documents for previous versions of this file, from
     * the database and the gateway.
     *
     * @param Plagscan_Document $dirtyDocument
     */
    public function clearPreviousVersions($dirtyDocument) {
        /** @var Submission $file */
        $file = SubmissionMapper::getInstance()->findById($dirtyDocument->getFileId());

        // Previous submissions of this file for this box and student.
        $fileList = SubmissionMapper::getInstance()->findByColumnList(
            array(
                'box_id' => $file->getSubmissionBox()->getId(),
                'owner'  => $file->getOwner()->getId(),
            )
        );

        // Previous Plagscan documents - delete them all, from here and from
        // Plagscan.
        /** @var Plagscan_Document[] $documentList */
        $documentList = $this->findByColumn('file_id', $fileList->getColumnValues('id'));
        foreach ($documentList as $document) {
            // Set the document as deleted.
            if ($this->gateway->delete($document->getPlagscanId())) {
                $this->delete($document);
            }
        }
    }

    /**
     * Populates a Plagscan document out of a submission. Doesn't actually save
     * it, though.
     *
     * @param  Submission        $submission
     * @return Plagscan_Document
     */
    public function createFromSubmission(Submission $submission) {
        $document = new Plagscan_Document();
        $document->setFileId($submission->getId());
        $document->setFile(array(
            'id'   => $submission->getId(),
            'name' => $submission->getFilename(),
            'file' => $submission->getTempLocation(),
        ));
        return $document;
    }
}