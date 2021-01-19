/**
 * Mixin for code which needs to apply to all feeds.
 */
export default {
    /**
     * Events that any user profile feed may emit.
     */
    events: {
        AWARDS_UPDATED: 'awards-updated'
    },

    methods: {
        /**
         * Emit an event to all feeds.
         *
         * @param {String} event The name of the event that is being raised
         * @param {Array}  args  Any additional args for this event's listeners
         */
        emitToAllFeeds(event, ...args) {
            window.achievementFeed.$emit(event, ...args);
            window.goalFeed.$emit(event, ...args);
            window.activityFeed.$emit(event, ...args);
        }
    }
}
