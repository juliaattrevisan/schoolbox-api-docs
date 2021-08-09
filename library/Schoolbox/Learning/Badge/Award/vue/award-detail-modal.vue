<script>
import BadgeImage from './../../vue/badge-image';
import goalMixin from './goal-mixin';
import RelativeTime from '@schoolboxLearningMomentEvidence/relative-time';
import RevealModal from '@schoolbox/reveal-modal'
import toastMixin from '@schoolbox/toast-mixin';
import userProfileMixin from '../../../vue/user-profile/mixin';
import axios from 'axios';
import { animateBadgeImage } from '../js/animateBadgeImage'

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
        /**
         * Has this award's expiry date been reached?
         *
         * @returns {Boolean}
         */
        isExpired() {
            if (this.award.expiresAt === null) {
                return false;
            }
            return moment(this.award.expiresAt).isBefore(moment(), 'd');
        }
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
        open (award) {
            this.award = award;
            this.$refs.modal.open();
            this.checkForBadgeAnimation();
        },

        /**
         * check to see whether this is the first time the user has viewed this badge achievement
         * and if so animate it to add a little sparkle
         */
        checkForBadgeAnimation () {
            this.$nextTick(() => {
                if (this.canAnimateBadgeImage()) {
                    this.animateBadgeImage();
                } else {
                    this.checkBadgeAnimationCleanedUp();
                }
            });
        },

        /**
         * To determine whether we should show the animation
         * 
         * @returns { Boolean }
         */
        canAnimateBadgeImage () {
            return this.award.viewedOn === null //hasn't been looked at before
                && this.award.recipient.id === window.schoolboxUser.id //is the awarded user
                && this.award.isGoal === false; //has been awarded
        },

        /**
         * animate the badge and notify that it's been viewed so 
         * it won't be animated again
         */
        animateBadgeImage () {
            const badgeImage = this.$refs.badgeImage.$el.querySelector('img');
            animateBadgeImage(badgeImage);
            this.award.viewedOn = ''; //viewedOn will currently only be updated on page reload due to the way awards are currently displayed
            axios.post(`/learning/badge/award/${this.award.id}/view`); //to update viewedOn in the backend
        },

        /**
         * The badge could have been animated, in which case we'll ensure 
         * it displays as expected when the user re-opens the modal window after
         * closing it the first time
         */
        checkBadgeAnimationCleanedUp () {
            const badgeImageClone = this.$refs.badgeImage.$el.querySelector('[data-is-badge-clone]');
            if (badgeImageClone) { //remove the animated background
                badgeImageClone.remove();
            }
            const badgeImage = this.$refs.badgeImage.$el.querySelector('img');
            badgeImage.style = ''; //gsap applies some style attributes, which should be cleared again
        },

        /**
         * Award the achievement
         */
        awardAchievement () {
            this.toastActivate('Awarding...');
            this.convertToAchievement(this.award)
                .then((response) => {
                    this.toastSuccess('Achievement awarded');

                    // Once awarded: update the award within this object
                    this.award = response.data[0];

                    // And post a message to the other components that this
                    // user's awards have updated
                    // TODO investigate Vuex state management
                    this.emitUpdateToAllUserProfileFeeds();
                })
                .catch(() => {
                    this.toastError('Failed to award achievement')
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
                    <badge-image :badge="award.badge" :css="getCss(award)" ref="badgeImage"></badge-image>
                    <template v-if="award.count > 1">
                        <div class="award-count" :title="'Awarded ' + award.count + ' times'">x{{ award.count }}</div>
                    </template>
                </div>
                <!-- Only show the count if one was provided in the award -->
                <h2>{{ award.badge.name }}</h2>
                <p class="meta">Category: {{ award.badge.category.name }}</p>
                <p class="meta">
                    <template v-if="award.isGoal">
                        Goal set
                    </template>
                    <template v-else>
                        Awarded
                    </template>
                    <relative-time :datetime="award.awardedAt" :key="`${award.id}-awardedAt`"></relative-time>
                    by
                    <a :href="award.awardedBy._links.profile">{{ award.awardedBy.fullName }}</a><template v-if="award.expiresAt">,
                        <template v-if="isExpired">expired</template>
                        <template v-else>expires</template>
                        <relative-time :datetime="award.expiresAt" date-only :key="`${award.id}-expiresAt`"></relative-time>
                    </template>
                </p>
                <p class="meta" v-if="award.isGoal && award.award">
                    Goal has been achieved <relative-time :datetime="award.award.awardedAt" :key="`${award.id}-goalAwardedAt`"></relative-time>
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
