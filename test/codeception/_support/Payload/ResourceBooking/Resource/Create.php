<?php

//to https://vagrant.dev.schoolbox.cloud/resource-booking/item
//from https://vagrant.dev.schoolbox.cloud/resource-booking/item/add
/*
 * -----------------------------6731915664397490031596721782
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
    }
    
    /** @inheritDoc*/
    public function getUrl(): string
    {
        return "/resource-booking/item";
    }
}
    