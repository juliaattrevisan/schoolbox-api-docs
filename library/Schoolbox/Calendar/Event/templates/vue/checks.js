const ATTENDANCE_STATUS_PENDING = 1;
const ATTENDANCE_STATUS_ACCEPTED = 2;
const ATTENDANCE_STATUS_DECLINED = 3;

const ATTENDANCE_LEVELS_NONE = 0;
const ATTENDANCE_LEVELS_INVITE_ONLY = 1;
const ATTENDANCE_LEVELS_OPEN = 2;

export default {

    /**
     * Decide whether you can RSVP to an invitation or, alternatively,
     * insert yourself into the guest list (same approach either way)
     *
     * @param currentAttendanceLevel  int      current event's Attendance level
     * @param guests                  array    a list of guests currently invited
     * @param currentUserId           int      the currently logged in user's id
     * @param currentAttendanceStatus int|null your current attendance status,
     * @param maxAttendees            int|null maximum number of people who can attend,
     * @param numAvailable            int      the quantity of free spaces available,
     * @return boolean
     */
    canRsvp(currentAttendanceLevel, guests, currentUserId, currentAttendanceStatus, maxAttendees, numAvailable)
    {

        /* Attendance Levels prohibit availability of the RSVP buttons
         * If you have access to see the buttons, check if attendance quantities let you rsvp
         */
        if (!this.showRsvpButtons(currentAttendanceLevel, guests, currentUserId)) {
            return false;
        }

        /* No limit; you can always rsvp
         */
        if (!this.isLimitedAttendance(maxAttendees)) {
            return true;
        }

        /* If you've not responded at all, or are currently Pending
         */
        if (!this.isInvited(currentAttendanceStatus) || this.isPending(currentAttendanceStatus)) {

            /* If your currentAttendanceStatus is null, or Pending, and there are available spaces,
             * then you haven't yet responded and are able to insert yourself into the list or
             * accept/decline the invitation respectively
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
     * Decide when the RSVP buttons are available for usage
     *
     * Further check in canRsvp() determines whether the current attendance settings
     * prohibit you further due to limits, etc.
     *
     * @return boolean
     */
    showRsvpButtons(currentAttendanceLevel, guests, currentUserId)
    {

        /* If the event attendance is Open, show the buttons
         */
        if (currentAttendanceLevel === ATTENDANCE_LEVELS_OPEN) {
            return true;
        }

        /* If the event attendance is Invite Only, and you are on the list,
         * show the buttons
         */
        if (currentAttendanceLevel === ATTENDANCE_LEVELS_INVITE_ONLY) {
            for (let i = 0; i < guests.length; i++) {
                if (guests[i].id === currentUserId) {
                    return true;
                }
            }
        }

        /* Otherwise, hide the buttons
         */
        return false;
    },

    /**
     * If your currentAttendanceStatus is null, you aren't invited/on the list
     * Conversely, if your currentAttendanceStatus is not null, you are invited/on the list
     *
     * @param currentAttendanceStatus
     * @returns boolean
     */
    isInvited: function(currentAttendanceStatus) {
        return currentAttendanceStatus !== null;
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
    },

    /**
     * Does the attendance meta show?
     *
     * @param currentAttendanceLevel
     * @return {boolean}
     */
    showAttendanceMeta: function (currentAttendanceLevel) {
        return currentAttendanceLevel === ATTENDANCE_LEVELS_OPEN || currentAttendanceLevel === ATTENDANCE_LEVELS_INVITE_ONLY;
    },

    /**
     * Is this an open-attendance event?
     *
     * @param currentAttendanceLevel
     * @return {boolean}
     */
    isOpen: (currentAttendanceLevel) => currentAttendanceLevel === ATTENDANCE_LEVELS_OPEN,

    /**
     * Is this an invite-only event?
     *
     * @param currentAttendanceLevel
     * @return {boolean}
     */
    isInviteOnly: (currentAttendanceLevel) => currentAttendanceLevel === ATTENDANCE_LEVELS_INVITE_ONLY,
}
