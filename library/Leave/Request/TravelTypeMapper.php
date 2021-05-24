<?php

/**
 * Mapper for the travel type lookup table.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
class Leave_Request_TravelTypeMapper extends Schoolbox\Mapper
{
    /**
     * {@inheritdoc}
     */
    protected function mapping()
    {
        $this->hasTable('lu_leave_request_travel_type');
        $this->hasModel(Leave_Request_TravelType::class);
        $this->hasMap(
            array(
                'column'     => 'id',
                'property'   => 'id',
                'type'       => self::TYPE_INTEGER,
                'identifier' => true
            )
        );
        $this->hasMap(
            array(
                'column'   => 'name',
                'property' => 'name',
                'type'     => self::TYPE_STRING,
            )
        );

        $this->hasMap(array(
            'column' => 'sequence',
            'property' => 'sequence',
            'type' => self::TYPE_INTEGER,
        ));
    }
}
