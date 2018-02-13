<?php
/**
 * Datetime (no timezone) column type.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 
namespace Schoolbox\Column;
use Alaress\Cache;
use \DateTime as DateObject;
use \Alaress_Date_Format;

class Datetime implements IColumnType {
	protected $input_format  = 'Y-m-d H:i:s';
	protected $output_format = 'Y-m-d H:i:s';

	/** @var Cache|Alaress_Date_Format[] */
	private $cache = [];

    /**
     * This value will hydrate to null.
     */
    protected const ZERO_VALUE = '0000-00-00 00:00:00';

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->cache = new Cache(function ($value, $inputFormat) {
            if (!isset($value) || static::ZERO_VALUE === $value) {
                return new \Alaress_Date_NullDate();
            }
            return new Alaress_Date_Format($value, $inputFormat);
        });
    }


    /**
	 * @param string            $value
	 * @param \Schoolbox\Mapper $mapper
	 *
	 * @return Alaress_Date_Format
	 */
	public function hydrate($value, $mapper = null) {
        try {
            return clone $this->cache->get($value, [ $value, $this->input_format ]);

        } catch (\Exception $e) {
            return new \Alaress_Date_NullDate();
        }
	}

	/**
	 * Datetime mapper can dehydrate either a DateTime object, an
	 * Alaress_Date_Format object or a string (used to create DateTime object,
	 * then formatted).
	 *
	 * @param mixed             $value
	 * @param \Schoolbox\Mapper $mapper
	 *
	 * @return string
	 */
	public function dehydrate($value, $mapper = null) {
        if ($value instanceof \Alaress_Date_NullDate) {
            return null;
        }
		if ($value instanceof Alaress_Date_Format) {
			return $value->getUsingFormat($this->output_format);
		}
		if (is_string($value)) {
			try {
				$value = new DateObject($value);
			}
			catch (\Exception $e) {
			}
		}
		if ($value instanceof DateObject) {
			return $value->format($this->output_format);
		}
		return null;
	}
}
