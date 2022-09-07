<script>

import axios from "axios";
import BadgeImage from '../../vue/badge-image';
import Toast from "@schoolboxVue/components/toast";

// Vue-select boilerplate
// TODO consider wrapping in our own component
import vSelect from "vue-select";
import debounce from "lodash/debounce";
import "vue-select/dist/vue-select.css";
import DynamicInput from '@schoolboxVue/components/ckeditor/dynamic-input';
import cdnPrefixMixin from '@schoolboxVue/components/cdn-prefix-mixin';

// Render a custom template for the deselect icon.
// Partially documented on https://github.com/sagalbot/vue-select/issues/994
import Deselect from '@schoolboxVue/components/selectize/Deselect';
vSelect.props.components.default = () => ({ Deselect });

import {calculateExpiryDate} from '../../vue/expiry-helper'

const EVENTS = {
    done: 'done',
    cancel: 'cancel'
};

// The number of milliseconds to wait between the user's input and a search for
// users.
const SEARCH_RESULT_RETRIEVE_DELAY = 350;

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
    mixins: [
        cdnPrefixMixin,
    ],
    components: {
        BadgeImage,
        Toast,
        vSelect,
        DynamicInput
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
            default: '/ajax/user.php',
        },

        /**
         * The query string parameter to submit the user search parameter
         * within, when performing a user search.
         */
        userSearchQueryParam: {
            type: String,
            default: 'query',
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
                return {roleTypeID: 'student',};
            },
        },

        /**
         * Placeholder text to display in the form's v-select input, before any
         * users are selected.
         */
        placeholder: {
            type: String,
            default: 'Search for users to award achievement to',
        },

        /**
         * Toast activation message
         */
        toastActivateMessage: {
            type: String,
            default: 'Awarding achievement...',
        },


        /**
         * Toast success message
         */
        toastSuccessMessage: {
            type: String,
            default: 'Awarded achievement',
        },

        /**
         * Toast failure message
         */
        toastFailureMessage: {
            type: String,
            default: 'Awarding achievement failed',
        },

        /**
         * The number of days that this award will remain for before it expires,
         * or null if it will never expire.
         */
        expiry: {
            type: Number,
            default: null,
        },

        siteVersion: String
    },
    data() {
        return {
            options: [],
            selected: [],
            reason: null,
            isFormSubmitActive: false,
            /**
             * so dynamic-input.vue is only in the dom while the modal is active
             * we do this to avoid issues around ckeditor instantiation coupled with modals
             * where opening, closing, then re-opening a modal causes the ckeditor to break
             */
            isModalOpen: false,
            reason: '', //the reason the badge(s) are being awarded
        }
    },
    computed: {
        /**
         * Is the badge form's submit button disabled?
         * It is disabled if there are no users to submit, or a form submission is already in progress.
         *
         * @returns {Boolean}
         */
        isSubmitButtonDisabled () {
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
        expiryDate (fromDate) {
            return calculateExpiryDate(this.expiry, fromDate);
        },
    },
    methods: {
        /**
         * Search callback for the selectize.
         *
         * This method is debounced so that the search doesn't trigger until
         * the user has stopped typing for `SEARCH_RESULT_RETRIEVE_DELAY`
         * milliseconds.
         *
         * @param {String}   query   The search string
         * @param {Function} loading A function to call when the loading state
         *                           changes (accepts a Boolean)
         */
        onSearch: debounce(function(query, loading) {
            if (query.length < 3) {
                return;
            }
            loading(true);
            let params = this.userSearchAdditionalParams;
            params[this.userSearchQueryParam] = query;
            axios
                .get(this.userSearchEndpoint, {params: params})
                .then(response => this.options = response.data)
                .finally(() => loading(false))
        }, SEARCH_RESULT_RETRIEVE_DELAY),


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

            // /* TODO How to reach into ckeditor value
            //  */
            // let reason = document.querySelector('textarea[name="reason"]').value;
            // let reason = CKEDITOR.instances['reason'].getData() maybe

            console.log(this.reason);

            axios.post(this.endpoint, {users: this.selected, reason: this.reason})
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
                        () => this.$emit(EVENTS.done)
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
         * To keep track of the reason, that's being updated by dynamic-input.vue
         *
         * @param { string }
         */
        onReasonChanged (newValue) {
            this.reason = newValue;
        },

        /**
         * The modal is now completely closed so reset
         */
        onModalClosed () {
            this.isModalOpen = false;
            this.reason = '';
        },
    },
}
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

        <div>
            <p class="meta">Provide evidence or narrative on how the achievement was earned.</p>

            <dynamic-input
                v-if="true"
                preset-unique-id="reason"
                inputName="reason"
                singular="''"
                :value="''"
                :allow-edit="true"
                :allow-submit="false"
                :spawn-immediately="true"
                :is-processing="true"
                :cdn-prefix="this.cdnPrefix"
                :site-version="this.siteVersion"
                @value-changed="onReasonChanged"
            ></dynamic-input>
        </div>

        <!--
        @slot The expiry message for this badge award
        @binding {Date|null} expiryDate An actual expiry date object
        -->
        <slot name="expiryMessage" :expiryDate="expiryDate"></slot>

        <v-select class="schoolbox-vue-selectize"
                  :options="options"
                  :clear-search-on-blur="function() { return true; }"
                  :clear-search-on-select="true"
                  :close-on-select="false"
                  multiple
                  @search="onSearch"
                  label="name"
                  :placeholder="placeholder"
                  v-model="selected">
            <template #option="option">
                <span class="d-center">
                    {{ option.name }}
                </span>
            </template>
            <template #selected-option="option">
                <span class="selected d-center">
                    {{ option.name }}
                </span>
            </template>
        </v-select>
        <ul class="flex-list buttons">
            <li>
                <button @click.prevent="onSubmit" class="submit" :disabled="isSubmitButtonDisabled">
                    <!-- @slot The text for this badge award form's confirm button -->
                    <slot name="confirmButton">
                      Award Achievement
                    </slot>
                </button>
            </li>
            <li><button @click.prevent="onCancel">Cancel</button></li>
        </ul>

        <toast ref="toast"></toast>
    </div>
</template>
