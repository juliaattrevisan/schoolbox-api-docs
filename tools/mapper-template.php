#!/usr/bin/php
<?php

/**
 * Builds Mapper and Model code from a db table
 * This allows you to copypasta the output into a usable mapper and model class
 *
 * Usage: ./mapper-template.php -t=comms_news_article
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

require_once __DIR__ . '/../conf/core/require.php';

$kernel = new \Schoolbox\Application\Daemon\Kernel(__FILE__);

$opts = getopt('t::');

if (empty($opts['t'])) {
    die('');
}

$table = preg_replace('`[^a-zA-Z_]`', '', $opts['t']);

$stmt = Database::getInstance()->prepare("DESCRIBE `$table`;");
$stmt->execute();

$mapper = array();
$model  = array();

while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    try {
        $mapperType = determineMapperType($r);
        $modelType = determineModelType($mapperType);

        if ($mapperType == '\Schoolbox\Mapper::TYPE_MODEL' && $r['Key'] != 'PRI') {
            $camel = substr(score2camel($r['Field']), 0, -2);
        } else {
            $camel = score2camel($r['Field']);
        }

        $model[] = array(
            'property' => $camel,
            'type'     => $modelType
        );

        $mapper['table'] = $table;
        $mapper['model'] = '\Schoolbox\\' . score2pascal($table);

        $mapper['map'][] = array(
            'field'     => $r['Field'],
            'property'  => $camel,
            'type'      => $mapperType,
            'selftype'  => str_replace('\Schoolbox\Mapper', 'self', $mapperType),
            'id'        => $r['Key'] == 'PRI'
        );
    } catch (Exception $e) {
        echo $e->getMessage(), "\n";
    }
}

/* output model */
echo "\nclass {$mapper['model']} {\n";

// properties themselves
foreach ($model as $p) {
    printf("\n	/** @var %s */\n	private $%s;\n", $p['type'], $p['property']);
}

// getters and setters, with return $this; on setters
foreach ($model as $p) {
    $type = $p['type'] === 'object' ? ucfirst($p['property']) . '\Model' : $p['type'];
    printf(
        "

	/**
	 * @param %s $%s
	 * @return \$this
	 */
	public function set%s(%s $%s): self
	{
		\$this->%s = $%s;
		return \$this;
	}
",
        $type,
        $p['property'],
        ucfirst($p['property']),
        $type,
        $p['property'],
        $p['property'],
        $p['property']
    );

    printf(
        "

	/**
	 * @return %s
	 */
	public function get%s(): ?%s
	{
		return \$this->%s;
	}
",
        $type,
        ucfirst($p['property']),
        $type,
        $p['property']
    );
}

echo "
}

class {$mapper['model']}Mapper extends \\Schoolbox\\Mapper {

	protected function mapping() {
		\$this->hasTable('{$mapper['table']}');
		\$this->hasModel('{$mapper['model']}');";

foreach ($mapper['map'] as $mapping) {
    echo "
		\$this->hasMap(array(
			'column'     => '{$mapping['field']}',
			'property'   => '{$mapping['property']}',
			'type'       => {$mapping['selftype']}";

    if ($mapping['id']) {
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
function determineMapperType($r)
{
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

    if (substr($scoreField, -3) == '_id') {
        return '\Schoolbox\Mapper::TYPE_MODEL';
    }

    foreach ($map as $from => $to) {
        // iterating from longest to shortest, there should be no collisions
        if (strpos($r['Type'], $from) !== false) {
            return $to;
        }
    }
    throw new Exception("Not found: {$r['Field']}");
}


function determineModelType($mapperType)
{
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
