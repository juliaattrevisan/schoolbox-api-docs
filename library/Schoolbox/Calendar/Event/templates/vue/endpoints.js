export const ENDPOINTS = Object.freeze({
    /* Event API URLs
     */
    URL_EVENT_SOURCE: "/calendar/ajax/full",
    URL_EVENT_UPDATE: "/calendar/ajax/update",
    URL_EVENT_MOVE: "/calendar/event/{eventId}/move",
    URL_EVENT_DELETE: "/calendar/event/{eventId}/delete",
    URL_EVENT_EXPORT: "/calendar/export.php",

    /* Event Modify screen
     */
    URL_EVENT_MODIFY_FORM: "/calendar/event/{eventId}/modify",
    URL_EVENT_MODIFY_FORM_ACTION: "/calendar/modifyForm.php",

    /* Attendance Payload for data about a given event
     */
    URL_ATTENDANCE_PAYLOAD: "/calendar/event/attendance/{eventId}",

    /* Inline alteration of attendees on event detail screen
     */
    URL_ATTENDANCE_CREATE: "/calendar/event/attendance/{eventId}/create",
    URL_ATTENDANCE_DELETE: "/calendar/event/attendance/{eventId}/delete",

    /* RSVP Status mutation routes
     */
    URL_ATTENDANCE_RSVP_ACCEPT: "/calendar/event/attendance/{eventId}/accept",
    URL_ATTENDANCE_RSVP_DECLINE: "/calendar/event/attendance/{eventId}/decline",
    URL_ATTENDANCE_RSVP_DELETE: "/calendar/event/attendance/{eventId}/delete",

    /* Selectize endpoint
     */
    URL_SELECTIZE: "/ajax/user.php",

    /**
     * Generate a path via const
     *
     * Provision of the returnUrl allows us to return to the place
     * from whence thou cam'st
     *
     * @param {String}      str       the base path
     * @param {Number|null} eventId   the event's id: if set will replace
     *                                "{eventId}" in the generated path
     * @param {String|null} returnUrl a url to visit after saving: if set will
     *                                be appended as the "returnUrl" query param
     * @return {String}
     */
    generateUrlFromConst(str, eventId, returnUrl) {
        let url = str;

        if (eventId !== undefined && eventId !== null) {
            url = url.replace("{eventId}", String(eventId));
        }

        if (returnUrl !== undefined && returnUrl !== null) {
            // url could be one of:
            // an absolute URL: set the return URL, and return the full URL
            // a relative URL: set the return URL, and return the relative part
            // an invalid URL: just append a query string param for return URL
            try {
                // URL is absolute
                let urlObject = new URL(url);
                urlObject.searchParams.set("returnUrl", returnUrl);
                url = urlObject.toString();
            } catch (e1) {
                try {
                    // URL is relative
                    let urlObject = new URL(url, "https://base.url");
                    urlObject.searchParams.set("returnUrl", returnUrl);
                    url = urlObject.pathname + urlObject.search + urlObject.hash;
                } catch (e2) {
                    // URL is invalid
                    url += "?returnUrl=" + returnUrl;
                }
            }
        }

        return url;
    },

    /**
     * Get some payload data about a given event
     *
     * @param eventId
     * @return string
     */
    payloadPath: function (eventId) {
        return this.generateUrlFromConst(ENDPOINTS.URL_ATTENDANCE_PAYLOAD, eventId, null);
    },

    /**
     * Onwards to the modify screen
     *
     * @param eventId
     * @return string
     */
    modifyPath: function (eventId) {
        return this.generateUrlFromConst(ENDPOINTS.URL_EVENT_MODIFY_FORM, eventId, btoa(window.location.pathname));
    },

    /**
     * Create new attendances
     *
     * @param eventId
     * @return string
     */
    createAttendancePath: function (eventId) {
        return this.generateUrlFromConst(ENDPOINTS.URL_ATTENDANCE_CREATE, eventId, btoa(window.location.pathname));
    },

    /**
     * Delete attendances
     *
     * @param eventId
     * @return string
     */
    deleteAttendancePath: function (eventId) {
        return this.generateUrlFromConst(ENDPOINTS.URL_ATTENDANCE_DELETE, eventId, btoa(window.location.pathname));
    },

    /**
     * RSVP Accept
     *
     * @param eventId
     * @return string
     */
    rsvpAcceptPath: function (eventId) {
        return this.generateUrlFromConst(ENDPOINTS.URL_ATTENDANCE_RSVP_ACCEPT, eventId, btoa(window.location.pathname));
    },

    /**
     * RSVP Decline
     *
     * @param eventId
     * @return string
     */
    rsvpDeclinePath: function (eventId) {
        return this.generateUrlFromConst(
            ENDPOINTS.URL_ATTENDANCE_RSVP_DECLINE,
            eventId,
            btoa(window.location.pathname),
        );
    },

    /**
     * RSVP Delete
     *
     * @param eventId
     * @return string
     */
    rsvpDeletePath: function (eventId) {
        return this.generateUrlFromConst(ENDPOINTS.URL_ATTENDANCE_RSVP_DELETE, eventId, btoa(window.location.pathname));
    },
});
