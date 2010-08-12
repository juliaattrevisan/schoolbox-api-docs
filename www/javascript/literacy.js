function countdownTo(launch) {

    var date = new Date();

    var current = {
        year: date.getFullYear(),
        month: date.getMonth(),
        day: date.getDate(),
        hour: date.getHours(),
        minute: date.getMinutes(),
        second: date.getSeconds()
    }

    /* Check expiration
     */

    /* Note that javascript months are indexed from 0-11
     */
    var currentDateUTC = new Date.UTC(current.year, current.month, current.day, current.hour, current.minute, current.second);
    var launchDateUTC = new Date.UTC(launch.year, launch.month-1, launch.day, launch.hour, launch.minute, launch.second);
    var launchDate = new Date(launch.year, launch.month-1, launch.day, launch.hour, launch.minute, launch.second);
    
    if ((launchDateUTC - currentDateUTC) < 0) {
        launchMessage();
    }
    else {
        $j('#countdown').countdown({
            until: launchDate,
            onExpiry: launchMessage
        });
    }
}

/**
 * Execute launch.
 */
function launchMessage() {
    var launchText = "It's here - <strong>Literacy and Numeracy Week</strong>";

    $j('#countdownWrapper').html(launchText);
}