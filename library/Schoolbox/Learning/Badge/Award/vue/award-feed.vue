<script>
import axios from "axios";
import AwardDetailModal from './award-detail-modal';
import AwardWithdrawModal from "./award-withdraw-modal";
import foundationMixin from './foundation-mixin';
import goalMixin from './goal-mixin';
import RelativeTime from '@schoolboxLearningMomentEvidence/relative-time';

export default {

    /**
     * Name.
     * The name of the component that we have here. This name will be used in the container
     * to spawn this component.
     */
    name: "award-feed",

    /**
     * Mixins.
     * Basically traits for vue; allows you to share javascript between components in the same
     * manner as a php trait. They must already be imported at the top of the file and will
     * be superpositioned on top of this javascript here, inside export default {}
     */
    mixins: [
        foundationMixin,
        goalMixin
    ],

    /**
     * Props.
     * Immutable props that were passed to this component, usually via twig, in our setup.
     * These are the hyphen-separated vars which are magically computed into camelcase elsewhere.
     */
    props: [
    ],

    /**
     * Components.
     * Any other vue components you also want to use here; they must already be imported
     * at the top of the file.
     */
    components: {
        AwardDetailModal,
        AwardWithdrawModal,
        RelativeTime
    },

    /**
     * Data.
     * Mutable properties that can be accessed from within this component via "this" keyword
     * Note that v-if and v-for must never be on the same node together, as this overrides
     * the meaning of "this" in a way you don't expect.
     */
    data() {
        return {
            data: [],
            loading: true,
        }
    },

    /**
     * Mounted.
     * Basically, when vue has done all its inits and is ready to go
     * kind of like window.onload
     */
    mounted: function () {
        this.updateAwards();
    },

    /**
     * Computed properties.
     * Access the results of a function as if they were a property of an object.
     */
    computed: {
        config: () => window.learningBadgeAwardFeedConfig
    },

    /**
     * Methods.
     * Arbitrary javascript functions which operate on the args provided, just like any
     * other ordinary javascript that you might have written before.
     */
    methods: {

        /**
         * Open a modal to show the badge that aws given as an award
         *
         * @param award
         */
        openDetailModal(award) {
            this.$refs.awardDetailModal.open(award);
        },

        /**
         * Open a confirmdialog for withdrawing a badge
         *
         * @param award
         */
        openWithdrawModal(award) {
            this.$refs.awardWithdrawModal.open(award);
        },

        /**
         * Generate an identifier for tots
         *
         * @param award
         * @returns {string}
         */
        generateIdentifier(award) {
            return 'header-options-' + award.badge.category.id + '-' + award.badge.id;
        },

        /**
         * Fetch the list of awards
         */
        updateAwards() {
            let self = this;

            /* Access the feed endpoint, grab the awards for the user
             */
            axios.get(this.config.routes.getAwardFeed, {})
                .then(response => {
                    self.data = [];
                    self.$nextTick(() => {
                        self.data = response.data;
                    });
                })
                .finally(() => this.loading = false);
        },

        /**
         * Decide if this Award has a tot menu
         * If there aren't any actions you can undertake, hide the tot
         */
        hasTot(award) {
            return this.canWithdraw(award);
        },

        /**
         * Decide if the current user can Withdraw an Award
         *
         * @param award
         * @returns {boolean}
         */
        canWithdraw(award) {
            return Boolean(award._links.withdraw);
        }
    }
}
</script>
<template>
    <div>
        <template v-if="this.loading">
            <dl class="tabs">
                <dd class="skeleton" v-for="n in 3">
                    <a></a>
                </dd>
            </dl>
        </template>
        <template v-else-if="this.data.categories">
            <section class="awards">
                <template v-for="category in this.data.categories">
                    <h2 class="subheader content">{{ category.name }}</h2>

                    <div class="v-card-list">
                        <div class="v-card card wrap-down" v-for="award in category.awards">
                            <div class="card-feature-image">
                                <a href="#" @click.prevent="openDetailModal(award)">
                                    <img :src="award.badge.image._links.image + '&size=constrain200'" :class="getCss(award)"/>
                                </a>
                            </div>
                            <div class="card-content">
                                <h3 class="no-margin"><a href="#" @click.prevent="openDetailModal(award)">{{ award.badge.name }} ({{ award.count }})</a></h3>
                                <p class="meta">
                                    <template v-if="award.isGoal">
                                        Goal set
                                    </template>
                                    <template v-else>
                                        Awarded
                                    </template>

                                    <relative-time :datetime="award.awardedAt"></relative-time>
                                </p>
                            </div>
                            <div class="card-actions">
                                <div class="card-spacer"></div>

                                <template v-if="!award.isGoal">

                                    <template v-if="hasTot(award)">
                                    <nav>
                                        <a class="icon-more" title="More" :data-dropdown="generateIdentifier(award)"></a>
                                    </nav>
                                    <ul :id="generateIdentifier(award)" class="f-dropdown" data-dropdown-content>

                                        <!--
                                         -- Only show Withdraw button if you can actually do it
                                         -- Students can't withdraw their own Achievement
                                         -->
                                        <template v-if="canWithdraw(award)">
                                        <li><a @click.prevent="openWithdrawModal(award)" class="icon-close" title="Withdraw">Withdraw</a></li>
                                        </template>
                                    </ul>
                                    </template>
                                </template>
                            </div>
                        </div>
                    </div>

                    <hr />
                </template>
            </section>
        </template>
        <template v-else>
            <div class="empty-state">
                <i class="icon-advanced-list"></i>
                <p>There haven't been any goals set nor any achievements received.</p>
            </div>
        </template>

        <award-detail-modal ref="awardDetailModal"></award-detail-modal>
        <award-withdraw-modal ref="awardWithdrawModal" v-on:withdraw-complete="updateAwards()"></award-withdraw-modal>
    </div>
</template>
