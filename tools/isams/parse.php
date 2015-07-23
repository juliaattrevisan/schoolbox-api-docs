#!/usr/bin/php
<?php
/**
 * Script for parsing iSAMS Batch API
 */

require_once __DIR__ . '/../../include/Bootstrap.php';

$reader = new XMLReader();
$reader->open($argv[1]);

$iterator = new Alaress\Xml\ReaderIterator($reader);
foreach (
    new Alaress\Xml\Filter\Path(
        new Alaress\Xml\Filter\Element($iterator),
        [
            '/iSAMS/PupilManager/CurrentPupils/Pupil',
            '/iSAMS/PupilManager/Contacts/Contact/Pupils/Pupil'
        ]
    ) as $readerState
) {
    var_dump($readerState->getReader()->readInnerXml());
}
