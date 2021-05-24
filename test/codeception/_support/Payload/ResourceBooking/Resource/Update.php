<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Payload\ResourceBooking\Resource;

use Payload;
use Payload\PostTrait;

class Update extends Payload\Request
{
    use PostTrait;

    var $resItemId;

    public function __construct(\Schoolbox\ResourceBooking\Item\Model $res_item)
    {
        $this->resItemId = $res_item->getId();
        $this->setReferer('/resource-booking/item/' . $this->resItemId . '/edit');
        $this->setID($this->resItemId);
        $this->setDescription('<p>Testing Description</p>');
        $this->setMinLeadTime('5', '1', '2');
        $this->setAllowedRole('1', true);
        $this->setAllowedRole('2', true);
        $this->setResourceCat('1');
        $moderationType = \Schoolbox\ResourceBooking\Item\ModerationType\Model::TYPE_OPEN;
        $this->setModerationTypeId($moderationType);
    }

    public function setID(string $id): void
    {
        $this->setFormParam('id', $id);
    }

    public function setResourceName(string $title)
    {
        $this->setFormParam('name', $title);
    }

    public function setDescription(string $desc)
    {
        $this->setFormParam('description', $desc);
    }

    public function setRoomCode(string $roomCode)
    {
        $this->setFormParam('room_code', $roomCode);
    }

    public function setResourceCat(string $resource_category_id)
    {
        $this->setFormParam('resource_category_id', $resource_category_id);
    }

    public function setAllowedRole(string $role_id, bool $allowed)
    {
        $role_feild = 'role[' . $role_id . ']';
        if ($allowed) {
            $this->setFormParam($role_feild, '1');
        } else {
            $this->setFormParam($role_feild, '0');
        }
    }

    public function setModerationTypeId(string $resource_moderation_type_id)
    {
        $this->setFormParam('resource_moderation_type_id', $resource_moderation_type_id);
    }

    public function setMinLeadTime(string $minute, string $hour = null, string $day = null)
    {
        $this->setTime('min_lead_time', $minute, $hour, $day);
    }

    public function setMinResTime(string $minute, string $hour = null, string $day = null)
    {
        $this->setTime('min_reservation_time', $minute, $hour, $day);
    }

    public function setMaxResTime(string $minute, string $hour = null, string $day = null)
    {
        $this->setTime('max_reservation_time', $minute, $hour, $day);
    }

    private function setTime(string $timeType, string $minute, string $hour = null, string $day = null)
    {
        $this->setFormParam($timeType . '[minute]', $minute);
        if (isset($hour)) {
            $this->setFormParam($timeType . '[hour]', $hour);
        }
        if (isset($day)) {
            $this->setFormParam($timeType . '[day]', $day);
        }
    }

    /** @inheritDoc*/
    public function getUrl(): string
    {
        return "/resource-booking/item/" . $this->resItemId . "/update";
    }
}
