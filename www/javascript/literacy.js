function countdownTo(launch) {
  var launchDate = new Date(launch.year, launch.month-1, launch.day, launch.hour, launch.minute, launch.second);
    $j('#countdown').countdown({
        until: launchDate,
        alwaysExpire: true,
        onExpiry: launchMessage
    });
}

/**
 * Execute launch.
 */
function launchMessage() {
    var launchText = "It's here - <strong>Literacy and Numeracy Week</strong>";

    $j('#countdownWrapper').html(launchText);
}