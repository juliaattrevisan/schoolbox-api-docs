#!/usr/bin/php
<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once(__DIR__ . '/../library/Bootstrap.php');

$opts = getopt('t::');

if(empty($opts['t'])) {
	die('');
}

$table = preg_replace('`[^a-zA-Z_]`', '', $opts['t']);

$stmt = Database::getInstance()->prepare("DESCRIBE `$table`;");
$stmt->execute();

$mapper = array();
$model  = array();

while($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
	try {
		$mapperType = determineMapperType($r);
		$modelType = determineModelType($mapperType);

		if($mapperType == '\Schoolbox\Mapper::TYPE_MODEL' && $r['Key'] != 'PRI') {
			$camel = substr(score2camel($r['Field']), 0, -2);
		}
		else {
			$camel = score2camel($r['Field']);
		}

		$model[] = array(
			'property' => $camel,
			'type'     => $modelType
		);

		$mapper['table'] = $table;
		$mapper['model'] = '\Schoolbox\\'.score2pascal($table);

		$mapper['map'][] = array(
			'field'     => $r['Field'],
			'property'  => $camel,
			'type'      => $mapperType,
			'selftype'  => str_replace('\Schoolbox\Mapper', 'self', $mapperType),
			'id'        => $r['Key'] == 'PRI'
		);
	}
	catch(Exception $e) {
		echo $e->getMessage(), "\n";
	}

}

/* output model */
echo "\nclass {$mapper['model']} {\n";

// properties themselves
foreach($model as $p) {
	printf("\n	/** @var %s */\n	protected $%s;\n", $p['type'], $p['property']);
}

// getters and setters, with return $this; on setters
foreach($model as $p) {
	printf("

	/**
	 * @param %s $%s
	 * @return self
	 */
	public function set%s($%s) {
		\$this->%s = $%s;
		return \$this;
	}
",
		$p['type'], $p['property'], ucfirst($p['property']), $p['property'], $p['property'], $p['property']
	);

	printf("

	/**
	 * @return %s
	 */
	public function get%s() {
		return \$this->%s;
	}
",
		$p['type'], ucfirst($p['property']), $p['property']
	);

}

echo "
}

class {$mapper['model']}Mapper extends \\Schoolbox\\Mapper {

	protected function mapping() {
		\$this->hasTable('{$mapper['table']}');
		\$this->hasModel('{$mapper['model']}');";

foreach($mapper['map'] as $mapping) {
	echo "
		\$this->hasMap(array(
			'column'     => '{$mapping['field']}',
			'property'   => '{$mapping['property']}',
			'type'       => {$mapping['selftype']}";

	if($mapping['id']) {
		echo ",
			'identifier' => true";
	}

	echo "
		));
";

}


echo "
	}
}
";




/**
 * @param array $r
 * @return string
 * @throws Exception
 */
function determineMapperType($r) {
	$map = array(
		"enum('true','false')"  => '\Schoolbox\Mapper::TYPE_BOOLEAN',
		'datetime'              => '\Schoolbox\Mapper::TYPE_DATETIME',
		'decimal'               => '\Schoolbox\Mapper::TYPE_DECIMAL',
		'tinyint'               => '\Schoolbox\Mapper::TYPE_BOOLEAN',
		'varchar'               => '\Schoolbox\Mapper::TYPE_STRING',
		'float'                 => '\Schoolbox\Mapper::TYPE_DECIMAL',
		'date'                  => '\Schoolbox\Mapper::TYPE_DATE',
		'text'                  => '\Schoolbox\Mapper::TYPE_STRING',
		'enum'                  => '\Schoolbox\Mapper::TYPE_STRING',
		'char'                  => '\Schoolbox\Mapper::TYPE_STRING',
		'int'                   => '\Schoolbox\Mapper::TYPE_INTEGER',
	);

	$scoreField = camel2score($r['Field']);

	if(substr($scoreField, -3) == '_id') {
		return '\Schoolbox\Mapper::TYPE_MODEL';
	}

	foreach($map as $from => $to) {
		// iterating from longest to shortest, there should be no collisions
		if(strpos($r['Type'], $from) !== false) {
			return $to;
		}
	}
	throw new Exception("Not found: {$r['Field']}");
}


function determineModelType($mapperType) {
	$map = array(
		'\Schoolbox\Mapper::TYPE_BOOLEAN'   => 'bool',
		'\Schoolbox\Mapper::TYPE_DATETIME'  => '\Alaress_Date_Format',
		'\Schoolbox\Mapper::TYPE_DECIMAL'   => 'float',
		'\Schoolbox\Mapper::TYPE_STRING'    => 'string',
		'\Schoolbox\Mapper::TYPE_DATE'      => '\Alaress_Date_Format',
		'\Schoolbox\Mapper::TYPE_INTEGER'   => 'int',
		'\Schoolbox\Mapper::TYPE_MODEL'     => 'object'
	);

	return isset($map[$mapperType]) ? $map[$mapperType] : 'null';

}

/**
 * @param $camel
 * @param string $delim
 * @return string
 */
function camel2score($camel, $delim = '_') {
	$xpl = preg_split('/([[:upper:]][[:lower:]]+)/', $camel, null, PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);
	return strtolower(implode($delim, $xpl));
}

/**
 * @param $score
 * @param string $delim
 * @return string
 */
function score2camel($score, $delim = '_') {
	return lcfirst(score2pascal($score, $delim));
}

/**
 * @param $score
 * @param string $delim
 * @return string
 */
function score2pascal($score, $delim = '_') {
	$xpl = explode($delim, $score);
	foreach($xpl as &$v) {
		$v = ucfirst($v);
	}
	return implode('', $xpl);
}


