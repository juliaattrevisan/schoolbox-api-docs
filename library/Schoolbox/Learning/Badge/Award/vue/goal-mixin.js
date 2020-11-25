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
        }
    }
}

