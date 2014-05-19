#!/usr/bin/php
<?php
use Alaress\Scheduler\Trigger\TriggerInterface;
use Alaress\WorkerCrew\Pooling\DefaultDispatchStrategy;

/**
 * Tool for assessing the contents of the pqueue (SQL table `scheduleq` and determining any reason as to why the
 * records contained there cannot be processed)
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once(__DIR__ . '/../../include/Bootstrap.php');

$d = new \Alaress\Cli\Debug(STDOUT, array());

$stmt = Database::getInstance()->prepare("
	SELECT *
	FROM scheduleq
");

$stmt->execute();

while($r = $stmt->fetch(PDO::FETCH_ASSOC)) {

	try {

		$hash = $r['id_hash'];
		$command = $r['command'];
		$encodedIdentifier = $r['identifier'];
		$encodedData = $r['data'];

		$d->v("[$hash] Command is '$command'");

		$identifier = unserialize($encodedIdentifier);
		$data = unserialize(unserialize($encodedData));

		if($identifier === false) {
			$d->v("[$hash] Failed to unserialize identifier");
			throw new Exception();
		}
		else {
			switch(gettype($identifier)) {
				case 'object':
					$d->v("[$hash] Identifier is instance of ".get_class($identifier), $identifier instanceof TriggerInterface ? '+' : '*');
					break;
				case 'resource':
					$d->v("[$hash] Identifier is resource", '*');
					throw new Exception();
				default:
					$d->v("[$hash] Identifier is ".gettype($identifier).": '$identifier'", '*');
					$err = true;
			}
		}




		if($data === false) {
			$d->v("[$hash] Failed to unserialize data");
			throw new Exception();
		}

		if($identifier instanceof TriggerInterface) {
			// data should be an array
			$correctType = gettype($data) == 'array';
			$d->v("[$hash] Data is ".gettype($data), $correctType ? '+' : '*');

			if(!$correctType) {
				throw new Exception();
			}

			if(in_array($command, array(DefaultDispatchStrategy::TASK_PUBLISH, DefaultDispatchStrategy::TASK_DIGEST))) {
				if(count($data) != 4) {
					$d->v("[$hash] Datacount for publication must be 4, got ".count($data), '*');
					throw new Exception();
				}

				if(is_callable($data[0])) {
					$d->v("[$hash] First argument is callable", '+');
				}
				else {
					$d->v("[$hash] First argument is NOT callable", '*');
				}

				if(is_object($data[1])) {
					$d->v("[$hash] Second argument is object, instance of ".get_class($data[1]), '+');
				}
				else {
					$d->v("[$hash] First argument is NOT object", '*');
				}

				if($data[2] instanceof \Schoolbox\User) {
					$d->v("[$hash] Third argument is instance of Schoolbox\\User", '+');
				}
				else {
					$d->v("[$hash] Third argument is NOT instance of Schoolbox\\User", '*');
				}

				if(is_null($data[3]) || is_array($data[3])) {
					$d->v("[$hash] Fourth argument is an acceptable diff", '+');
				}
				else {
					$d->v("[$hash] Fourth argument is NOT an acceptable diff", '*');
				}

			}
		}

		$d->v("[$hash] Probably can process");

	}
	catch(Exception $e) {
		$d->v("[$hash] Probably cannot process, should delete", '!');
	}

}