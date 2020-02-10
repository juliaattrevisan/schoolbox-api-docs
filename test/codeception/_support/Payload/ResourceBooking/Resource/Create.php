<?php

//to https://vagrant.dev.schoolbox.cloud/resource-booking/item
//from https://vagrant.dev.schoolbox.cloud/resource-booking/item/add
/*
Content-Disposition: form-data; name="id"


-----------------------------6731915664397490031596721782
Content-Disposition: form-data; name="name"

stuff12
-----------------------------6731915664397490031596721782
Content-Disposition: form-data; name="description"

<p>a;lkgrnhaoegr;</p>

-----------------------------6731915664397490031596721782
Content-Disposition: form-data; name="room_code"


-----------------------------6731915664397490031596721782
Content-Disposition: form-data; name="resource_category_id"

7
-----------------------------6731915664397490031596721782
Content-Disposition: form-data; name="role[1]"

0
-----------------------------6731915664397490031596721782
Content-Disposition: form-data; name="role[1]"

1
-----------------------------6731915664397490031596721782
Content-Disposition: form-data; name="role[2]"

0
-----------------------------6731915664397490031596721782
Content-Disposition: form-data; name="role[2]"

1
-----------------------------6731915664397490031596721782
Content-Disposition: form-data; name="role[3]"

0
-----------------------------6731915664397490031596721782
Content-Disposition: form-data; name="role[4]"

0
-----------------------------6731915664397490031596721782
Content-Disposition: form-data; name="role[5]"

0
-----------------------------6731915664397490031596721782
Content-Disposition: form-data; name="role[6]"

0
-----------------------------6731915664397490031596721782
Content-Disposition: form-data; name="role[7]"

0
-----------------------------6731915664397490031596721782
Content-Disposition: form-data; name="resource_moderation_type_id"

1
-----------------------------6731915664397490031596721782
Content-Disposition: form-data; name="min_lead_time[day]"


-----------------------------6731915664397490031596721782
Content-Disposition: form-data; name="min_lead_time[hour]"


-----------------------------6731915664397490031596721782
Content-Disposition: form-data; name="min_lead_time[minute]"


-----------------------------6731915664397490031596721782
Content-Disposition: form-data; name="min_reservation_time[day]"


-----------------------------6731915664397490031596721782
Content-Disposition: form-data; name="min_reservation_time[hour]"


-----------------------------6731915664397490031596721782
Content-Disposition: form-data; name="min_reservation_time[minute]"


-----------------------------6731915664397490031596721782
Content-Disposition: form-data; name="max_reservation_time[day]"


-----------------------------6731915664397490031596721782
Content-Disposition: form-data; name="max_reservation_time[hour]"


-----------------------------6731915664397490031596721782
Content-Disposition: form-data; name="max_reservation_time[minute]"


-----------------------------6731915664397490031596721782--

 */

namespace Payload\ResourceBooking\Resource;

use Payload;

use Payload\PostTrait;

class Create extends Payload\Request
{
    use PostTrait;
    
    /**
     */
    public function __construct()
    {
        $this->setReferer('/resource-booking/item/add');
        $this->setID('');
    }
    
    public function setID(string $id):void
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
        if($allowed)
        {
            $this->setFormParam($role_feild, '1');
        } 
        else
        {
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
        if (isset($hour))
        {
            $this->setFormParam($timeType . '[hour]', $hour);
        }
        if (isset($day))
        {
            $this->setFormParam($timeType . '[day]', $day);
        }
    }
    
    /** @inheritDoc*/
    public function getUrl(): string
    {
        return "/resource-booking/item";
    }
}