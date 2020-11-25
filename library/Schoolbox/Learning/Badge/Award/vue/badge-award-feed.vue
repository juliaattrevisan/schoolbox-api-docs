<script>
import axios from "axios";
import BadgeDetailModal from './badge-detail-modal';
import goalMixin from './goal-mixin';
import RelativeTime from '@schoolboxLearningMomentEvidence/relative-time';

export default {

    /**
     * Name.
     * The name of the component that we have here. This name will be used in the container
     * to spawn this component.
     */
    name: "badge-award-feed",

    /**
     * Mixins.
     * Basically traits for vue; allows you to share javascript between components in the same
     * manner as a php trait. They must already be imported at the top of the file and will
     * be superpositioned on top of this javascript here, inside export default {}
     */
    mixins: [
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
        BadgeDetailModal,
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
        let self = this;

        /* Access the feed endpoint, grab the awards for the user
         */
        axios.get(this.config.routes.getAwardFeed, {})
            .then(response => {
                self.data = response.data;
            })
            .finally(() => this.loading = false);
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
        openModal(award) {
            this.$refs.badgeDetailModal.open(award);
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
                                <a href="#" v-on:click="openModal(award)">
                                    <img :src="award.badge.image._links.image + '&size=constrain200'" :class="getCss(award)"/>
                                </a>
                            </div>
                            <div class="card-content">
                                <h3 class="no-margin"><a href="#" v-on:click="openModal(award)">{{ award.badge.name }} ({{ award.count }})</a></h3>
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

        <badge-detail-modal ref="badgeDetailModal"></badge-detail-modal>
    </div>
</template>





