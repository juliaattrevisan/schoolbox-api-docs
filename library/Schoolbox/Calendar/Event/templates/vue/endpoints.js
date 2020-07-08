export const ENDPOINTS = Object.freeze({

    /* Event API URLs
     */
    URL_EVENT_SOURCE: '/calendar/ajax/full',
    URL_EVENT_UPDATE: '/calendar/ajax/update',
    URL_EVENT_DELETE: '/calendar/event/{eventId}/delete',
    URL_EVENT_EXPORT: '/calendar/export.php',

    /* Event Modify screen
     */
    URL_EVENT_MODIFY_FORM: '/calendar/event/{eventId}/modify',
    URL_EVENT_MODIFY_FORM_ACTION: '/calendar/modifyForm.php',

    /* Inline alteration of attendees on event detail screen
     */
    URL_ATTENDANCE_CREATE: '/calendar/event/attendance/{eventId}/create',
    URL_ATTENDANCE_DELETE: '/calendar/event/attendance/{eventId}/delete',

    /* RSVP Status mutation routes
     */
    URL_ATTENDANCE_RSVP_ACCEPT: '/calendar/event/attendance/{eventId}/accept',
    URL_ATTENDANCE_RSVP_DECLINE: '/calendar/event/attendance/{eventId}/decline',
    URL_ATTENDANCE_RSVP_DELETE: '/calendar/event/attendance/{eventId}/delete',

    /* Selectize endpoint
     */
     URL_SELECTIZE: '/ajax/user.php',

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
    },


    /**
     * Onwards to the modify screen
     */
    modifyPath: function(eventId) {
        return this.generateUrlFromConst(ENDPOINTS.URL_EVENT_MODIFY_FORM, eventId, btoa(window.location.pathname));
    },


    /**
     * Create new attendances
     */
    createAttendancePath: function(eventId) {
        return this.generateUrlFromConst(ENDPOINTS.URL_ATTENDANCE_CREATE, eventId, btoa(window.location.pathname));
    },


    /**
     * Delete attendances
     */
    deleteAttendancePath: function(eventId) {
        return this.generateUrlFromConst(ENDPOINTS.URL_ATTENDANCE_DELETE, eventId, btoa(window.location.pathname));
    },


    /**
     * RSVP Accept
     */
    rsvpAcceptPath: function(eventId) {
        return this.generateUrlFromConst(ENDPOINTS.URL_ATTENDANCE_RSVP_ACCEPT, eventId, btoa(window.location.pathname));
    },


    /**
     * RSVP Decline
     */
    rsvpDeclinePath: function(eventId) {
        return this.generateUrlFromConst(ENDPOINTS.URL_ATTENDANCE_RSVP_DECLINE, eventId, btoa(window.location.pathname));
    },


    /**
     * RSVP Delete
     */
    rsvpDeletePath: function(eventId) {
        return this.generateUrlFromConst(ENDPOINTS.URL_ATTENDANCE_RSVP_DELETE, eventId, btoa(window.location.pathname));
    }
});
