<?php

/**
 * QuoteMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace DailyQuote;

use Schoolbox\Mapper\Exception\NotFound;

class QuoteMapper extends \Schoolbox\Mapper
{

    /**
     * Define column/property mapping
     *
     * @return Bool|void
     */
    protected function mapping()
    {
        $this->hasTable('dailyquote_data');
        $this->hasModel(Quote::class);

        $this->hasMap(array(
            'column'     => 'id',
            'property'   => 'id',
            'type'       => self::TYPE_INTEGER,
            'identifier' => true
        ));

        $this->hasMap(array(
            'column'     => 'description',
            'property'   => 'description',
            'type'       => self::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'     => 'author',
            'property'   => 'author',
            'type'       => self::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'     => 'created_at',
            'property'   => 'createdAt',
            'type'       => self::TYPE_DATETIME,
        ));

        $this->hasMap(array(
            'column'     => 'updated_at',
            'property'   => 'updatedAt',
            'type'       => self::TYPE_DATETIME,
        ));

        $this->hasMap(array(
            'column'     => 'deleted_at',
            'property'   => 'deletedAt',
            'type'       => self::TYPE_DATETIME,
        ));
    }

    /**
     * Find a random quote
     *
     * @throws \Exception
     */
    public function findRandomQuote()
    {
        $query = "
			SELECT {$this->getColumnString('q')}, RAND() AS sequence
			FROM `{$this->table}` q
			ORDER BY sequence
			LIMIT 1
		";

        $statement = $this->database->prepare($query);
        $statement->execute();

        $row = $statement->fetch();

        if ($row != false) {
            $quoteItem = $this->hydrate($row, 'q');

            return $quoteItem;
        }

        throw new NotFound();
    }
}
