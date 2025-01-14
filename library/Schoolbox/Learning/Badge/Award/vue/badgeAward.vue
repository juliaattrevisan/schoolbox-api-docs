<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
import SearchSelect from "@schoolboxVue/components/searchSelect/search-select.vue";
import axios from "axios";
import BadgeImage from "../../vue/badge-image";
import Toast from "@schoolboxVue/components/toast";
import cdnPrefixMixin from "@schoolboxVue/components/cdn-prefix-mixin";
import { generateAwardWithEvidenceUrl } from "../js/awardBadgeUtils";
import { calculateExpiryDate } from "../../vue/expiry-helper";

const EVENTS = {
    done: "done",
    cancel: "cancel",
};

// There's no official method for documenting a component's events,
// https://forum.vuejs.org/t/how-to-document-events-for-components/35541/3
// suggests the @emits tag.

/**
 * Award badge form.
 *
 * @emits done   The award badge action was successfully completed
 * @emits cancel The award badge action was cancelled
 */
export default {
    /**
     * Mixins.
     * Basically traits for vue; allows you to share javascript between components in the same
     * manner as a php trait. They must already be imported at the top of the file and will
     * be superpositioned on top of this javascript here, inside export default {}
     */
    mixins: [cdnPrefixMixin],
    components: {
        BadgeImage,
        Toast,
        SearchSelect,
    },
    /**
     * Props.
     * Immutable props that were passed to this component, usually via twig, in our setup.
     * These are the hyphen-separated vars which are magically computed into camelcase elsewhere.
     */
    props: {
        /**
         * The badge to display in this detail panel.
         */
        badge: {
            type: Object,
            default: null,
        },

        /**
         * The endpoint to post the badge award request to.
         *
         * It is expected that this will be either the badge's
         * "awardAsAchievement" or "setAsGoal" links.
         */
        endpoint: {
            type: String,
            default: null,
            required: true,
        },

        /**
         * The endpoint to which user search requests are submitted.
         */
        userSearchEndpoint: {
            type: String,
            default: "/ajax/user.php",
        },

        /**
         * Any additional query string parameters to submit to the user search
         * endpoint.
         *
         * By default, the user search shows students only.
         */
        userSearchAdditionalParams: {
            type: Object,
            default: () => {
                return { roleTypeID: "student" };
            },
        },

        /**
         * Toast activation message
         */
        toastActivateMessage: {
            type: String,
            default: "Awarding achievement...",
        },

        /**
         * Toast success message
         */
        toastSuccessMessage: {
            type: String,
            default: "Awarded achievement",
        },

        /**
         * Toast failure message
         */
        toastFailureMessage: {
            type: String,
            default: "Awarding achievement failed",
        },

        /**
         * The number of days that this award will remain for before it expires,
         * or null if it will never expire.
         */
        expiry: {
            type: Number,
            default: null,
        },

        /* Either "goal" or "achievement" depending on the context of this badge
         * being awarded - there are nuanced behaviours depending on whether this
         * badge was awarded as an achievemtn, or set as a goal
         */
        type: String,

        isModalOpen: Boolean,
        siteVersion: String,
    },
    data() {
        return {
            options: [],
            selected: [],
            isFormSubmitActive: false,
        };
    },
    computed: {
        /**
         * Is the badge form's submit button disabled?
         * It is disabled if there are no users to submit, or a form submission is already in progress.
         *
         * @returns {Boolean}
         */
        isSubmitButtonDisabled() {
            if (this.selected.length === 0) {
                return true;
            } else if (this.isFormSubmitActive) {
                return true;
            } else {
                return false;
            }
        },

        /**
         * Translate this badge award's expiry into a Date object.
         *
         * @param {Date|null} fromDate
         * @returns {Date|null}
         */
        expiryDate(fromDate) {
            return calculateExpiryDate(this.expiry, fromDate);
        },

        /**
         * return a string of comma separated user ids
         *
         * @returns {String}
         */
        selectedUserIds() {
            const userIdList = this.selected.map((user) => user.id);
            return userIdList.join(",");
        },
    },
    methods: {
        /**
         * Get the endpoint for the user search
         * @returns {String}- The endpoint for the user search
         */
        getEndpoint() {
            const searchUrl = new URL(this.userSearchEndpoint, window.location.href).toString();
            const searchParams = new URLSearchParams(this.userSearchAdditionalParams).toString();
            return `${searchUrl}?${searchParams}&`;
        },

        /**
         * Callback for when the badge award form is submitted.
         *
         * Posts to the `endpoint` property, then triggers this component's
         * "done" event.
         *
         * @emits done if the badge was successfully awarded (whether as an
         *             achievement or a goal)
         */
        onSubmit() {
            this.isFormSubmitActive = true;
            this.$refs.toast.activate(this.toastActivateMessage);

            axios
                .post(this.endpoint, { users: this.selected })
                .then(() => {
                    // Clear the select, so they don't show next time this opens
                    this.selected = [];

                    // The toast's lifetime is tied to this modal's lifetime.
                    // Wait until the toast times out to raise the done event.
                    this.$refs.toast.timeout(
                        this.toastSuccessMessage,
                        true,
                        Toast.DEFAULT_DISPLAY_DURATION_SUCCESSFUL,
                        Toast.DEFAULT_FADE_DURATION,
                        () => this.$emit(EVENTS.done),
                    );
                })
                .catch(() => {
                    this.$refs.toast.timeout(this.toastFailureMessage, false);
                })
                .finally(() => {
                    //can add another badge now, so the submit button can be clicked again
                    this.isFormSubmitActive = false;
                });
        },

        /**
         * Callback for when the badge award form is cancelled.
         *
         * @emits cancel
         */
        onCancel() {
            this.$emit(EVENTS.cancel);
        },

        /**
         * The modal is now completely closed so reset
         */
        onModalClosed() {
            this.isModalOpen = false;
        },

        /**
         * Generate a URL which references this page with all filters cleared.
         *
         * @return { string }
         */
        getEvidenceUrl() {
            return generateAwardWithEvidenceUrl(this.selectedUserIds, this.badge.id);
        },
    },
};
</script>
<template>
    <div>
        <!--
        @slot The header for this badge award form
        @binding {Object} badge The badge that is being shown in this component
        -->
        <slot name="header" :badge="badge"></slot>

        <div class="compact card small-12 badge-card">
            <div>
                <badge-image :badge="badge"></badge-image>
                <h3>{{ badge.name }}</h3>
            </div>
        </div>

        <!--
        @slot The expiry message for this badge award
        @binding {Date|null} expiryDate An actual expiry date object
        -->
        <slot name="expiryMessage" :expiryDate="expiryDate"></slot>
        <search-select
            v-model:selected="selected"
            label="Search"
            label-id="users"
            :endpoint="getEndpoint()"
            placeholder=""
        >
            <template #meta>
                <div class="meta">Search for users to award achievement to</div>
            </template>
        </search-select>
        <div class="component-action">
            <section>
                <a
                    v-if="type === 'achievement'"
                    title="Award with evidence"
                    class="button"
                    :href="getEvidenceUrl()"
                    :class="[isSubmitButtonDisabled ? 'disabled' : '']"
                    data-test="badge-award-evidence"
                >
                    Award with Evidence
                </a>
                <button :disabled="isSubmitButtonDisabled" @click.prevent="onSubmit" data-test="badge-award-confirm">
                    <slot name="confirmButton">Award</slot>
                </button>
            </section>
        </div>
        <toast ref="toast"></toast>
    </div>
</template>
