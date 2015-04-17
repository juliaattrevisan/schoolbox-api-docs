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

/**
 * Class Plagscan_DocumentMapper
 * @method Plagscan_Document createModel()
 */
class Plagscan_DocumentMapper extends Schoolbox\Mapper
{
    /** @var Submission\Mapper */
    protected $submissionMapper;

	/**
	 * @var Plagscan_Gateway
	 */
	private $gateway;

	protected function setup()
    {
		parent::setup();

		$plagscanApiKey   = $this->getConfigLoader()->get('plagscan_api_key');
		$plagscanUsername = $this->getConfigLoader()->get('plagscan_username');

        $this->submissionMapper = new Submission\FileMapper();

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

        $this->joins('file_id', Submission\FileMapper::class, 'id', null, null);

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
			$documentId = $this->gateway->submit($dirtyDocument->getFile());

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
        $file = $this->submissionMapper->findById($dirtyDocument->getFile()->getId());

        // Previous submissions of this file for this box and student.
        $fileList = $this->submissionMapper->findByColumnList(
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
     * @param  Submission\File $submission
     * @return Plagscan_Document
     */
    public function createFromSubmission(Submission\File $submission)
    {
        $plagMap = Plagscan_DocumentMapper::getInstance();
        return $plagMap->createModel()->setFile($submission);
    }
}