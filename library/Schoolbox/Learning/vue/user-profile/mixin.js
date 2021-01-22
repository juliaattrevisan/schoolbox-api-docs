import constants from "./constants";

/**
 * Mixin for code which needs to apply to all Vue instances on the user profile.
 */
export default {
    methods: {
        /**
         * Emit an update event to all user profile feeds.
         */
        emitUpdateToAllUserProfileFeeds() {
            this.emitToAllUserProfileFeeds(constants.events.AWARDS_UPDATED);
        },

        /**
         * Emit an event to all user profile feeds.
         *
         * @param {String} event The name of the event that is being raised
         * @param {Array}  args  Any additional args for this event's listeners
         */
        emitToAllUserProfileFeeds(event, ...args) {
            window.achievementFeed.$emit(event, ...args);
            window.goalFeed.$emit(event, ...args);
            window.activityFeed.$emit(event, ...args);
        }
    }
}
