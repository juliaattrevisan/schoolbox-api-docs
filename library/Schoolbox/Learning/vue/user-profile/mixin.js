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
            document.dispatchEvent(new CustomEvent(constants.events.AWARDS_UPDATED));
        },
    }
}
