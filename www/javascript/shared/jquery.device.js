/**
 * Decide whether the current page is being displayed on a
 * device with a small screen. fullcalendar requires
 * two different mutually exclusive configuration settings
 * to display properly on both desktop and mobile devices
 *
 * 40em marks Small breakpoint
 * Multiply by 16px base font size, js doesn't support em units
 *
 * @returns {boolean}
 */
function isSmallDevice() {
    if ($(window).width() <= 40*16) {
        return true;
    }

    return false;
}