const ATTENDANCE_STATUS_PENDING = 1;
const ATTENDANCE_STATUS_ACCEPTED = 2;
const ATTENDANCE_STATUS_DECLINED = 3;

export default {

    /**
     * Decide whether you can RSVP to an invitation or, alternatively,
     * insert yourself into the guest list (same approach either way)
     *
     * @param currentAttendanceStatus int|null your current attendance status,
     * @param maxAttendees            int|null maximum number of people who can attend,
     * @param numAvailable            int      the quantity of free spaces available,
     * @return boolean
     */
    canRsvp(currentAttendanceStatus, maxAttendees, numAvailable) {

        /* No limit; can always rsvp
         */
        if (!this.isLimitedAttendance(maxAttendees)) {
            return true;
        }

        /* If you've not responded at all, or are currently Pending
         */
        if (currentAttendanceStatus === null || this.isPending(currentAttendanceStatus)) {

            /* If your currentAttendanceStatus is null, or Pending, and there are available spaces,
             * then you haven't yet responded and are able to insert yourself into the list
             *
             * The buttons should be shown
             */
            if (numAvailable > 0) {
                return true;
            }

            /* If your currentAttendanceStatus is null, or Pending, and there aren't available spaces
             * then you can't insert yourself into the list, nor accept/decline
             *
             * The buttons should be hidden
             */
            return false;
        }

        /* If you've currently Accepted
         */
        if (this.isAccepted(currentAttendanceStatus)) {

            /* If your currentAttendanceStatus is Accepted, you can always decline
             * freeing up a spot for someone else to occupy
             *
             * The buttons should be shown
             */
            return true;
        }

        /* If you've currently Declined
         */
        if (this.isDeclined(currentAttendanceStatus)) {

            /* If your currentAttendanceStatus is Declined and there are available spaces
             * then you can change your mind and accept the invitation
             *
             * The buttons should be shown
             */
            if (numAvailable > 0) {
                return true;
            }

            /* If your currentAttendanceStatus is Declined and there aren't available spaces
             * then you can't change your mind; embrace it
             *
             * The buttons should be hidden
             */
            return false;
        }

        /* RSVP Buttons disabled by default
         */
        return false;
    },


    /**
     * Find out whether you've Accepted the event's invitation
     *
     * @param currentAttendanceStatus int|null
     * @return boolean
     */
    isPending: function(currentAttendanceStatus) {
        return currentAttendanceStatus === ATTENDANCE_STATUS_PENDING;
    },


    /**
     * Find out whether you've Accepted the event's invitation
     *
     * @param currentAttendanceStatus int|null
     * @return boolean
     */
    isAccepted: function(currentAttendanceStatus) {
        return currentAttendanceStatus === ATTENDANCE_STATUS_ACCEPTED;
    },


    /**
     * Find out whether you've Declined the event's invitation
     *
     * @param currentAttendanceStatus int|null
     * @return boolean
     */
    isDeclined: function(currentAttendanceStatus) {
        return currentAttendanceStatus === ATTENDANCE_STATUS_DECLINED;
    },


    /**
     * Find out whether this event's max_attendees setting means there are a limited
     * number of people who can attend; null means unlimited, 0 prohibits acceptance
     *
     * @param max int|null
     * @return boolean
     */
    isLimitedAttendance: function(max) {
        return max !== null;
    }
}
