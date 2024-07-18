UPDATE tour_step_modal
SET body = "<img src='{CDN_PREFIX}/images/tour/24_0_release_648x216.jpg' style='width:100%;margin-bottom:16px;'> <h3>Web Push Notifications</h3> <p>Make sure you never miss any events by subscribing to receive notifications directly to your device. To receive notifications on your Apple iPhone, make sure you install the web application.</p> <h3>Unit Planner</h3> <p>The Unit Planner has now been updated to allow you to update your units on the fly. This allows you to manage unit overviews, activities, lesson plans, and curriculum alignment from one place.</p> <p>To know more, see the <a href='help/sso/send?returnUrl=/homepage/5133'>release notes</a>.</p>"
WHERE tour_step_uuid = (
    SELECT first_step_uuid
    FROM tour
    WHERE title = '24.0 Release'
);

