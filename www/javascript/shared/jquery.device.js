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

/**
 * Check if current window width is medium or down.
 * At the time of writing - this can be used to check if the sidebar is collapsed by default or not.
 *
 * @returns {boolean}
 */
function windowIsMediumDown() {
    if (window.innerWidth <= 950) { // window.innerWidth is used to factor in scrollbars
        return true;
    }

    return false;
}