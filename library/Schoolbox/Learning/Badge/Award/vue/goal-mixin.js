import axios from "axios";

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
                return 'goal';
            }

            return '';
        },


        /**
         * Converts a goal to an achievement.
         *
         * @param {Object} award the award to convert to an achievement
         * @param {String} reason
         * @return {Promise} An AJAX request created by `axios`
         */
        convertToAchievement(award, reason) {
            return axios.request({
                url: award._links.goalAward,
                data: {users: [award.recipient], reason: reason, conversion: true},
                method: 'post',
                responseType: 'json'
            });
        }
    }
}

