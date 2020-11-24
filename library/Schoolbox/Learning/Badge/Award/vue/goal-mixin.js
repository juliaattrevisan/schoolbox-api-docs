/**
 * Goal-oriented mixins, so they're for detecting Goal badges
 * and dealing with their idiosyncrasies
 */
export default {
    methods: {

        /**
         * Decide if this Award is a goal or not
         */
        isGoal(award) {
            return 'goal' === award.status;
        },


        /**
         * Decide if this Award was a goal or an achievement
         * and provide the css class accordingly
         *
         * @param award
         * @returns string
         */
        getCss(award) {
            if (this.isGoal(award)) {
                return 'goal';
            }

            return '';
        }
    }
}

