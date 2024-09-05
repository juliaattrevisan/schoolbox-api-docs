<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
import BadgeImage from "./../../vue/badge-image";
import goalMixin from "./goal-mixin";
import RelativeTime from "@schoolboxLearningMomentEvidence/relative-time";
import SequentialRevealModal from "@schoolboxVue/components/sequential-reveal-modal";
import toastMixin from "@schoolboxVue/components/toast-mixin";
import userProfileMixin from "../../../vue/user-profile/mixin";
import axios from "axios";
import { animateBadgeImage } from "../js/animateBadgeImage";
import CodesList from "@schoolboxCurriculum/vue/codes-list";
import NodeDetails from "@schoolboxCurriculum/vue/node-details";
import MediaArticle from "@schoolboxVue/components/media-article";
import cdnPrefixMixin from "@schoolboxVue/components/cdn-prefix-mixin";
import { generateAwardWithEvidenceUrl } from "../js/awardBadgeUtils";

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
    mixins: [cdnPrefixMixin, goalMixin, toastMixin, userProfileMixin],

    /**
     * Props.
     * Immutable props that were passed to this component, usually via twig, in our setup.
     * These are the hyphen-separated vars which are magically computed into camelcase elsewhere.
     */
    props: {
        siteVersion: String,
        returnUrl: {
            type: String,
            default: null,
            required: false,
        },
    },

    emits: ["on-achievement-awarded"],

    /**
     * Components.
     * Any other vue components you also want to use here; they must already be imported
     * at the top of the file.
     */
    components: {
        BadgeImage,
        RelativeTime,
        SequentialRevealModal,
        CodesList,
        NodeDetails,
        MediaArticle,
    },

    /**
     * Data.
     * Mutable properties that can be accessed from within this component via "this" keyword
     * Note that v-if and v-for must never be on the same node together, as this overrides
     * the meaning of "this" in a way you don't expect.
     */
    data() {
        return {
            isModalOpen: false, //trigger to open/close the modal
            isNextSelected: false, // to trigger the next selection in the sequential reveal modal
            award: {},
            canvasWidth: 0,
            canvasHeight: 0,
            selectedNodeId: null, // number|null, the selected curriculum code to display further details for
        };
    },

    /**
     * Mounted.
     * Basically, when vue has done all its inits and is ready to go
     * kind of like window.onload
     */
    mounted: function () {},

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
            return dayjs(this.award.expiresAt).isBefore(dayjs(), "d");
        },
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
            this.isModalOpen = true;
            this.modelCheckInterval = setInterval(this.onModalCheck, 50); //be checking if the modal is on screen yet
        },

        /**
         * The <canvas> element (that shows the stars) requires accurate width/height to function properly.
         * As the canvas is within a modal, it's not currently in the dom, so we have to wait until
         * the foundation modal has displayed it, so we can ensure its dimensions are up to date.
         */
        onModalCheck() {
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
        checkForBadgeAnimation() {
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
        canAnimateBadgeImage() {
            return (
                this.award.viewedOn === null && //hasn't been looked at before
                this.award.recipient.id === window.schoolboxUser.id && //is the awarded user
                this.award.isGoal === false && //has been awarded
                this.award.badge.animate === true
            ); //badge can be animated
        },

        /**
         * This badge or another could have been animated, in which case we'll ensure
         * it displays as expected when the user re-opens the modal window after
         * closing it the first time.
         */
        checkBadgeAnimationCleanedUp() {
            const badgeImage = this.$refs.badgeImage.$el.querySelector("img");
            badgeImage.style = ""; //gsap applies some style attributes, which should be cleared again
        },

        /**
         * animate the badge and notify that it's been viewed so
         * it won't be animated again
         */
        animateBadgeImage() {
            const badgeImage = this.$refs.badgeImage.$el.querySelector("img");
            const awardCanvas = this.$refs.awardCanvas;
            animateBadgeImage(badgeImage, awardCanvas);
            this.award.viewedOn = ""; //viewedOn will currently only be updated on page reload due to the way awards are currently displayed
            axios.post(`/learning/badge/award/${this.award.id}/view`); //to update viewedOn in the backend
        },

        /**
         * Award the achievement
         */
        awardAchievement() {
            this.toastActivate("Awarding...");
            this.convertToAchievement(this.award)
                .then((response) => {
                    this.toastSuccess("Achievement awarded");

                    // Once awarded: update the award within this object
                    this.award = response.data;

                    this.$emit("on-achievement-awarded", this.award); //dispatch to react specifically to this badge achievement

                    // And post a message to the other components that this
                    // user's awards have updated
                    // @TODO investigate Vuex state management
                    this.emitUpdateToAllUserProfileFeeds();
                })
                .catch(() => {
                    this.toastError("Failed to award achievement");
                });
            this.isModalOpen = false;
        },

        /**
         * to open up the details modal
         *
         * @pararm { object }
         */
        onCodeSelected(node) {
            this.selectedNodeId = node.id;
            this.isNextSelected = true;
        },

        /**
         * The modal is now completely closed
         */
        onModalClosed() {
            this.isModalOpen = false;
        },

        /**
         * Redirect back to where we started and not the students profile.
         */
        returnToHere(value) {
            return value + "?returnUrl=" + btoa(window.location.pathname);
        },

        /**
         * Generate a URL which references this page with all filters cleared.
         *
         * @param { string }
         *
         * @return { string }
         */
        getEvidenceUrl(badgeId) {
            return generateAwardWithEvidenceUrl(this.award.recipient.id, badgeId);
        },

        /**
         * Generate returnUrl param
         * @returns {string}
         */
        getReturnUrl() {
            return this.returnUrl !== null ? `?returnUrl=${this.returnUrl}` : "";
        },

        /**
         * The existence of award.award let us know that
         * the award is a goal and it's been awarded
         *
         * @returns { boolean }
         */
        isAwardedGoal() {
            return this.award.award !== null;
        },

        /**
         * The award is a goal and its yet to be awarded,
         * so it could be awarded
         *
         * @returns { boolean }
         */
        isUnawardedGoal() {
            return this.award.isGoal && this.award.award === null;
        },

        /**
         * The existence of the link will determine if the
         * user has permission to access it
         *
         * @returns { boolean }
         */
        hasGoalAwardLink() {
            return this.award._links?.goalAward !== null;
        },

        /**
         * The existence of the link will determine if the
         * user has permission to access it
         *
         * @returns { boolean }
         */
        hasAwardUpdateLink() {
            return this.award._links?.awardUpdate !== null;
        },

        /**
         * If there's already evidence we can view linked to
         * the award.
         *
         * @param { object }
         *
         * @returns { boolean }
         */
        hasEvidence(award) {
            return award.reason !== null && award.reason.length > 0;
        },

        /**
         * If the award is an achieved goal that has evidence.
         *
         * @returns { boolean }
         */
        awardedGoalHasEvidence() {
            return this.award.award !== null && this.hasEvidence(this.award.award);
        },

        /**
         * If the award is an achieved goal that has evidence, we should show
         * the evidence linked to the achievement. Not the evidence that may
         * be linked to the goal. Accessing this link assumes the existence
         * and ability to add evidence has been checked elsewhere.
         *
         * @returns { string }
         */
        getViewEvidenceLink() {
            // check if it's a goal that's been achieved which has evidence, otherwise a goal/achievement with evidence
            const awardId = this.awardedGoalHasEvidence(this.award) ? this.award.award.id : this.award.id;
            return `/learning/badge/award/${awardId}/viewEvidence${this.getReturnUrl()}`;
        },

        /**
         * If the award is an achieved goal, we should create evidence on the achievement,
         * not on the goal. Accessing this link assumes the existence
         * and ability to add evidence has been checked elsewhere.
         *
         * @returns { string }
         */
        getAddEvidenceLink() {
            // check if it's a goal that's been achieved so link to the achievement evidence, not the goal evidence, otherwise just link to the goal/achievement link
            const awardId = this.isAwardedGoal(this.award) ? this.award.award.id : this.award.id;
            return `/learning/badge/award/${awardId}/update${this.getReturnUrl()}`;
        },
    },
};
</script>
<template>
    <sequential-reveal-modal
        size="medium"
        :is-open="isModalOpen"
        :is-next-selected="isNextSelected"
        @next-opened="isNextSelected = false"
        @closed="onModalClosed"
    >
        <template v-slot:sequence-1>
            <template v-if="award.badge">
                <div class="badge-modal">
                    <div class="badge-img-container" ref="badgeImgContainer">
                        <div class="badge-img-container__background">
                            <canvas :width="canvasWidth" :height="canvasHeight" ref="awardCanvas"></canvas>
                        </div>
                        <div class="badge-img-container__foreground">
                            <badge-image :badge="award.badge" :css="getCss(award)" ref="badgeImage"></badge-image>
                            <div
                                v-if="award.count > 1"
                                class="award-count"
                                :title="'Awarded ' + award.count + ' times'"
                            >
                                x{{ award.count }}
                            </div>
                        </div>
                    </div>
                    <!-- Only show the count if one was provided in the award -->
                    <h2>{{ award.badge.name }}</h2>
                    <p class="meta">Category: {{ award.badge.category.name }}</p>
                    <p class="meta">
                        <template v-if="award.isGoal"> Goal set </template>
                        <template v-else> Awarded </template>
                        <relative-time :datetime="award.awardedAt" :key="`${award.id}-awardedAt`"></relative-time>
                        by
                        <template v-if="award.awardedBy.isDeleted">{{ award.awardedBy.fullName }}</template>
                        <a v-else :href="award.awardedBy._links.profile">{{ award.awardedBy.fullName }}</a>
                        <template v-if="award.expiresAt"
                            >,
                            <template v-if="isExpired">expired</template>
                            <template v-else>expires</template>
                            <relative-time
                                :datetime="award.expiresAt"
                                date-only
                                :key="`${award.id}-expiresAt`"
                            ></relative-time>
                        </template>
                    </p>
                    <p class="meta" v-if="award.isGoal && award.award">
                        Goal has been achieved
                        <relative-time
                            :datetime="award.award.awardedAt"
                            :key="`${award.id}-goalAwardedAt`"
                        ></relative-time>
                    </p>
                    <template v-if="award.badge.curriculumNodes.length">
                        <p class="meta">
                            Curriculum Codes:
                            <codes-list :nodes="award.badge.curriculumNodes" @selected="onCodeSelected" />
                        </p>
                    </template>
                    <media-article v-if="award.badge.body" :content="award.badge.body"></media-article>
                    <div class="component-action">
                        <section>
                            <a
                                v-if="!award.isGoal && !hasGoalAwardLink()"
                                title="View award"
                                class="button"
                                :href="getViewEvidenceLink()"
                            >
                                View Award
                            </a>
                            <a
                                v-else-if="awardedGoalHasEvidence() || hasEvidence(award)"
                                title="View evidence"
                                class="button"
                                :href="getViewEvidenceLink()"
                            >
                                View Evidence
                            </a>
                            <a
                                v-else-if="hasAwardUpdateLink()"
                                title="Add evidence"
                                class="button"
                                :href="getAddEvidenceLink()"
                            >
                                Add Evidence
                            </a>
                            <a
                                v-if="hasGoalAwardLink() && isUnawardedGoal() && !hasEvidence(award)"
                                title="Award with evidence"
                                class="button"
                                :href="getEvidenceUrl(award.badge.id)"
                            >
                                Award with Evidence
                            </a>
                            <button v-if="hasGoalAwardLink() && isUnawardedGoal()" @click.prevent="awardAchievement">
                                Award
                            </button>
                        </section>
                    </div>
                </div>
            </template>
        </template>
        <template v-slot:sequence-2>
            <node-details v-if="selectedNodeId !== null" :node-id="selectedNodeId" />
        </template>
    </sequential-reveal-modal>
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
