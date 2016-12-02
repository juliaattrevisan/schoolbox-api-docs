<?php
/**
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */
use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan;

use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Model as SubmissionFile;

/**
 * @method Plagscan createModel()
 */
class Plagscan_DocumentMapper extends Schoolbox\Mapper
{
    /** @var SubmissionMapper */
    protected $submissionMapper;

	/**
	 * @var Plagscan_Gateway
	 */
	private $gateway;





}
