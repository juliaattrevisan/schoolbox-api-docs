<script>
import BadgeImage from './../../vue/badge-image';
import goalMixin from './goal-mixin';
import RelativeTime from '@schoolboxLearningMomentEvidence/relative-time';
import RevealModal from '@schoolbox/reveal-modal'
import toastMixin from '@schoolbox/toast-mixin';
import userProfileMixin from '../../../vue/user-profile/mixin';

/**
 * Award detail modal.
 *
 * @emits awards-updated When a goal is upgraded to an achievement.
 *                       (This is raised on the root Vue instance.)
 */
export default {

    /**
     * Name.
     * The name of the component that we have here. This name will be used in the container
     * to spawn this component.
     */
    name: "award-detail-modal",

    /**
     * Mixins.
     * Basically traits for vue; allows you to share javascript between components in the same
     * manner as a php trait. They must already be imported at the top of the file and will
     * be superpositioned on top of this javascript here, inside export default {}
     */
    mixins: [
        goalMixin,
        toastMixin,
        userProfileMixin
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
        BadgeImage,
        RelativeTime,
        RevealModal
    },

    /**
     * Data.
     * Mutable properties that can be accessed from within this component via "this" keyword
     * Note that v-if and v-for must never be on the same node together, as this overrides
     * the meaning of "this" in a way you don't expect.
     */
    data() {
        return {
            award: {}
        }
    },

    /**
     * Mounted.
     * Basically, when vue has done all its inits and is ready to go
     * kind of like window.onload
     */
    mounted: function () {
    },

    /**
     * Computed properties.
     * Access the results of a function as if they were a property of an object.
     */
    computed: {
    },

    /**
     * Methods.
     * Arbitrary javascript functions which operate on the args provided, just like any
     * other ordinary javascript that you might have written before.
     */
    methods: {

        /**
         * Open this modal.
         */
        open(award) {
            this.award = award;

            this.$refs.modal.open();
        },

        awardAchievement() {
            let self = this;
            self.toastActivate('Awarding...');
            self.convertToAchievement(self.award)
                .then((response) => {
                    self.toastSuccess('Achievement awarded');

                    // Once awarded: update the award within this object
                    self.award = response.data[0];

                    // And post a message to the other components that this
                    // user's awards have updated
                    // TODO investigate Vuex state management
                    self.emitUpdateToAllUserProfileFeeds();
                })
                .catch(() => {
                    self.toastError('Failed to award achievement')
                })
        }
    }
}
</script>
<template>
    <reveal-modal ref="modal" size="medium">
        <template v-if="award.badge">
            <div class="badge-modal">
                <div class="badge-img-container">
                    <badge-image :badge="award.badge" :css="getCss(award)"></badge-image>
                    <template v-if="award.count > 1">
                        <div class="award-count" :title="'Awarded ' + award.count + ' times'">x{{ award.count }}</div>
                    </template>
                </div>
                <!-- Only show the count if one was provided in the award -->
                <h2>{{ award.badge.name }}</h2>
                <p class="meta">Category: {{ award.badge.category.name }}</p>
                <p class="meta">
                    <template v-if="award.isGoal">
                        Goal set <relative-time :datetime="award.awardedAt" :key="award.id"></relative-time> by
                        <a :href="award.awardedBy._links.profile">{{ award.awardedBy.fullName }}</a>
                    </template>
                    <template v-else>
                        Awarded <relative-time :datetime="award.awardedAt" :key="award.id"></relative-time> by
                        <a :href="award.awardedBy._links.profile">{{ award.awardedBy.fullName }}</a>
                    </template>
                    <template v-if="award.expiresAt">
                        <template v-if="award.expiresAt && award.isGoal">
                            expires <relative-time :datetime="award.expiresAt" date-only :key="award.id"></relative-time>
                        </template>
                        <template v-else>
                            expires <relative-time :datetime="award.expiresAt" date-only :key="award.id"></relative-time>
                        </template>
                    </template>
                </p>
                <p class="meta" v-if="award.isGoal && award.award">
                    Goal has been achieved <relative-time :datetime="award.award.awardedAt" :key="award.id"></relative-time>
                </p>

                <article v-html="award.badge.body"></article>

                <template v-if="award.badge.curriculumNodes.length">
                    <hr />

                    <p class="meta">
                        Curriculum Codes:
                        <template v-for="curriculumNode in award.badge.curriculumNodes">
                            {{ curriculumNode.code }}
                        </template>
                    </p>
                </template>

                <div class="component-action">
                    <section>
                        <a v-if="award._links.goalAward && award.isGoal && !award.award"
                           class="button"
                           @click.prevent="awardAchievement">Award Achievement</a>
                    </section>
                </div>
            </div>
        </template>
    </reveal-modal>
</template>
