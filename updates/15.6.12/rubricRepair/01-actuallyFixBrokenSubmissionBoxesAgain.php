<?php
/**
 * Continuation of `updates/15.6.10/rubricRepair/01-actuallyFixBrokenSubmissionBoxes.php`
 *
 * That script attempted to fix broken boxes, but would trip up if it
 * encountered an excecption when doing so.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

require_once __DIR__ . '/../../../tools/submission/repairRubrics.php';
