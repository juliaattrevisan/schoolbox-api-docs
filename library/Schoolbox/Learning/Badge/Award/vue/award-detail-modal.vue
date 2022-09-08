<script>
import BadgeImage from './../../vue/badge-image';
import goalMixin from './goal-mixin';
import RelativeTime from '@schoolboxLearningMomentEvidence/relative-time';
import RevealModal from '@schoolboxVue/components/reveal-modal'
import toastMixin from '@schoolboxVue/components/toast-mixin';
import userProfileMixin from '../../../vue/user-profile/mixin';
import axios from 'axios';
import { animateBadgeImage } from '../js/animateBadgeImage'
import { ResizeObserver } from 'vue3-resize';
import 'vue3-resize/dist/vue3-resize.css';
import DynamicInput from '@schoolboxVue/components/ckeditor/dynamic-input';
import cdnPrefixMixin from '@schoolboxVue/components/cdn-prefix-mixin';

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
        cdnPrefixMixin,
        goalMixin,
        toastMixin,
        userProfileMixin
    ],

    /**
     * Props.
     * Immutable props that were passed to this component, usually via twig, in our setup.
     * These are the hyphen-separated vars which are magically computed into camelcase elsewhere.
     */
    props: {
        siteVersion: String
    },

    /**
     * Components.
     * Any other vue components you also want to use here; they must already be imported
     * at the top of the file.
     */
    components: {
        BadgeImage,
        RelativeTime,
        RevealModal,
        ResizeObserver, //to track any size changes to the parent container
        DynamicInput
    },

    /**
     * Data.
     * Mutable properties that can be accessed from within this component via "this" keyword
     * Note that v-if and v-for must never be on the same node together, as this overrides
     * the meaning of "this" in a way you don't expect.
     */
    data() {
        return {
            award: {},
            canvasWidth: 0,
            canvasHeight: 0,
            /**
             * so dynamic-input.vue is only in the dom while the modal is active
             * we do this to avoid issues around ckeditor instantiation coupled with modals
             * where opening, closing, then re-opening a modal causes the ckeditor to break
             */
            hasSelectedModify: false,
            /* Decide if the edit button for the Reason is available
             * (presuming you have access to the update route)
             * This toggles to false when you spawn the ckeditor
             */
            allowReasonEdit: true,

            reason: '', //the reason the badge(s) are being awarded
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
            return dayjs(this.award.expiresAt).isBefore(dayjs(), 'd');
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
            this.isModalOpen = true;
            this.award = award;
            this.$refs.modal.open();
            this.modelCheckInterval = setInterval(this.onModalCheck, 50); //be checking if the modal is on screen yet
        },

        /**
         * The <canvas> element (that shows the stars) requires accurate width/height to function properly.
         * As the canvas is within a modal, it's not currently in the dom, so we have to wait until
         * the foundation modal has displayed it, so we can ensure its dimensions are up to date.
         */
        onModalCheck () {
            const canvasDimensions = this.$refs.badgeImgContainer.getBoundingClientRect();
            if (canvasDimensions.width > 0) {
                clearInterval(this.modelCheckInterval);
                this.canvasWidth = Math.round(canvasDimensions.width);
                this.canvasHeight = Math.round(canvasDimensions.height);
                this.$nextTick(() => this.checkForBadgeAnimation());
            }
        },

        /**
         * check to see whether this is the first time the user has viewed this badge achievement
         * and if so animate it to add a little sparkle
         */
        checkForBadgeAnimation () {
            if (this.canAnimateBadgeImage()) {
                this.checkBadgeAnimationCleanedUp();
                this.animateBadgeImage();
            } else {
                this.checkBadgeAnimationCleanedUp();
            }
        },

        /**
         * To determine whether we should show the animation
         *
         * @returns { Boolean }
         */
        canAnimateBadgeImage () {
            return this.award.viewedOn === null //hasn't been looked at before
                && this.award.recipient.id === window.schoolboxUser.id //is the awarded user
                && this.award.isGoal === false //has been awarded
                && this.award.badge.animate === true; //badge can be animated
        },

        /**
         * This badge or another could have been animated, in which case we'll ensure
         * it displays as expected when the user re-opens the modal window after
         * closing it the first time.
         */
        checkBadgeAnimationCleanedUp () {
            const badgeImage = this.$refs.badgeImage.$el.querySelector('img');
            badgeImage.style = ''; //gsap applies some style attributes, which should be cleared again
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
         * Award the achievement
         */
        awardAchievement () {
            this.toastActivate('Awarding...');
            this.convertToAchievement(this.award, this.award.reason)
                .then((response) => {
                    this.toastSuccess('Achievement awarded');

                    // Once awarded: update the award within this object
                    this.award = response.data[0];

                    this.$emit('on-achievement-awarded', this.award); //dispatch to react specifically to this badge achievement

                    // And post a message to the other components that this
                    // user's awards have updated
                    // @TODO investigate Vuex state management
                    this.emitUpdateToAllUserProfileFeeds();
                })
                .catch(() => {
                    this.toastError('Failed to award achievement')
                })

            this.$refs.modal.close();
        },

        /**
         * to be able to udpate the reason using dynamic-input.vue
         */
        onEditReasonSelected () {
            this.hasSelectedModify = true;
            this.allowReasonEdit = false;
        },

        /**
         * To keep track of the reason, that's being updated by dynamic-input.vue
         *
         * @param { string }
         */
        onReasonChanged (newValue) {
            this.reason = newValue;
        },

        /**
         * post the changed reason value to the backend
         * and then close the modal
         *
         * @param { string }
         */
        onSaveReasonSelected (value) {
            console.warn(value);
        },

        /**
         * The modal is now completely closed
         */
        onModalClosed () {
            this.hasSelectedModify = false;
            this.allowReasonEdit = true;
            this.reason = '';
        },
    }
}
</script>
<template>
    <reveal-modal ref="modal" size="medium" @closed="onModalClosed">
        <template v-if="award.badge">
            <div class="badge-modal">
                <div class="badge-img-container" ref="badgeImgContainer">
                    <div class="badge-img-container__background">
                        <canvas :width="canvasWidth" :height="canvasHeight" data-badge-award-canvas></canvas>
                    </div>
                    <div class="badge-img-container__foreground">
                        <badge-image :badge="award.badge" :css="getCss(award)" ref="badgeImage"></badge-image>
                        <div v-if="award.count > 1" class="award-count" :title="'Awarded ' + award.count + ' times'">x{{ award.count }}</div>
                    </div>
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

                <!-- Only show the Reason section if there was something meaningful -->
                <template v-if="award.reason && award.reason.length">
                    <div class="actions-small-1">
                        <div class="list-item">
                            <h4>Evidence</h4>

                            <article v-html="award.reason" id="reason-proxy"></article>
                        </div>
                        <nav>
                            <!-- Provide the button to make the change -->
                            <!-- Nonzero-length reason gets a button -->
                            <button v-if="award._links.awardUpdate" class="icon-modify no-text modify-link" title="Update reason" @click.prevent="onEditReasonSelected" @keyup.enter="onEditReasonSelected"></button>
                        </nav>
                    </div>
                </template>

                <!-- If you can edit the reason... -->
                <template v-if="award._links.awardUpdate">
                    <!-- If there's no value here, provide empty state -->
                    <template v-if="!(award.reason && award.reason.length)">
                        <div class="empty-state" v-if="award._links.awardUpdate">
                            <p><a @click.prevent="onEditReasonSelected" @keyup.enter="onEditReasonSelected">Provide evidence</a> to demonstrate how this was achieved.</p>
                        </div>
                    </template>
                </template>

                <!-- If you have the ability to change the Reason, provide dynamic input -->
                <template v-if="award._links.awardUpdate">
                    <form :action="award._links.awardUpdate" method="post">
                        <dynamic-input
                            v-if="hasSelectedModify"
                            preset-unique-id="reason"
                            inputName="reason"
                            singular="reason"
                            :proxy="'reason-proxy'"
                            :value="award.reason"
                            :allow-edit="true"
                            :allow-submit="true"
                            :spawn-immediately="true"
                            :is-processing="true"
                            :cdn-prefix="this.cdnPrefix"
                            :site-version="this.siteVersion"
                            @post="onSaveReasonSelected"
                            @value-changed="onReasonChanged"
                        ></dynamic-input>
                    </form>
                </template>

                <template v-if="award.badge.curriculumNodes.length">
                    <hr />
                    <p class="meta">
                        Curriculum Codes:
                        <template v-for="(curriculumNode, index) in award.badge.curriculumNodes">
                            {{ curriculumNode.code }}<span v-if="index != award.badge.curriculumNodes.length-1">, </span>
                        </template>
                    </p>
                </template>

                <div class="component-action">
                    <section>
                        <a v-if="award._links.goalAward && award.isGoal && !award.award"
                           class="button"
                           @click.prevent="awardAchievement" @keyup.enter="awardAchievement">Award Achievement</a>
                    </section>
                </div>
            </div>
        </template>
    </reveal-modal>
</template>

<style scoped>

.modify-link {
    background: none;
    color: var(--content-ui-foreground);
    float: right;
}

article {
    max-width: none; /* allow to stretch full width in this context */
}

.save-button {
    margin-bottom: 1rem;
}

</style>
