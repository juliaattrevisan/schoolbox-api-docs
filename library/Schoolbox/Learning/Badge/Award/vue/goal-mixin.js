/**
 * Goal-oriented mixins, so they're for detecting Goal badges
 * and dealing with their idiosyncrasies
 */
export default {
    methods: {
        /**
         * Decide if this Award was a goal or an achievement
         * and provide the css class accordingly
         *
         * @param award
         * @returns string
         */
        getCss(award) {
            if (award.isGoal) {
                return "goal";
            }

            return "";
        },

        /**
         * Converts a goal to an achievement.
         *
         * @param {Object} award the award to convert to an achievement
         * @return {Promise} An AJAX request created by `fetch`
         */
        convertToAchievement(award) {
            return fetch(award._links.goalAward, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({ users: [award.recipient], conversion: true }),
            });
        },
    },
};
