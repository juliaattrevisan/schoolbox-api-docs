export const ENDPOINTS = Object.freeze({

    /* Event Modify screen
     */
    URL_EVENT_MODIFY_FORM: '/calendar/event/{eventId}/modify',

    /* Inline alteration of attendees on event detail screen
     */
    URL_ATTENDANCE_CREATE: '/calendar/event/attendance/{eventId}/create',
    URL_ATTENDANCE_DELETE: '/calendar/event/attendance/{eventId}/delete',

    /* RSVP Status mutation routes
     */
    URL_ATTENDANCE_RSVP_ACCEPT: '/calendar/event/attendance/{eventId}/accept',
    URL_ATTENDANCE_RSVP_DECLINE: '/calendar/event/attendance/{eventId}/decline',
    URL_ATTENDANCE_RSVP_DELETE: '/calendar/event/attendance/{eventId}/delete',

    /**
     * Generate a path via const
     *
     * Provision of the returnUrl allows us to return to the place
     * from whence thou cam'st
     *
     * @param str        the path where we need to replace a var
     * @param eventId    the event's id
     * @param returnUrl  a url to visit after saving
     * @return string
     */
    generateUrlFromConst(str, eventId, returnUrl) {
        let url = str.replace('{eventId}', eventId);

        if (returnUrl !== undefined) {
            url += '?returnUrl=' + returnUrl;
        }

        return url;
    }
});
