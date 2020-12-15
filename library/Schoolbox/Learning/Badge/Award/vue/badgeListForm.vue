<script>

import axios from 'axios';
import RevealModal from "@schoolbox/reveal-modal";
import BadgeImage from '../../vue/badge-image';
import BadgeList from '../../vue/badgeList';
import toastMixin from '@schoolbox/toast-mixin';

/**
 * Form for awarding badges to users, by selecting them from a list of badges.
 */
export default {

    /**
     * Mixins.
     * Basically traits for vue; allows you to share javascript between components in the same
     * manner as a php trait. They must already be imported at the top of the file and will
     * be superpositioned on top of this javascript here, inside export default {}
     */
    mixins: [toastMixin],

    components: {
        BadgeList,
        BadgeImage,
        RevealModal,
    },
    props: {
        /**
         * The route via which badges are retrieved.
         */
        getBadgesRoute: {
            type: String,
            required: true,
        },

        /**
         * The key within each selected badge's `_links` list to which requests
         * to create badges are posted.
         *
         * For example, if this is "awardAsAchievement", each selected badge's
         * `_links["awardAsAchievement"]` will have this component's users
         * posted to it.
         *
         * This is used to determine whether submitting the form awards
         * achievements or sets goals.
         */
        postAwardRouteLookup: {
            type: String,
            required: true,
        },

        /**
         * The planned recipients of the award.
         */
        users: {
            type: Array,
            required: true,
        },

        /**
         * Are the badges being awarded to the current user only?
         *
         * If so:
         * * do not show `this.users` in the confirm modal, even if they are set
         * * post to `this.postAwardRouteLookup` only once, instead of once for
         *   each `this.users`.
         */
        awardToSelfOnly: {
            type: Boolean,
            default: false,
        },

        /**
         * Where does the user get redirected to on successful save or
         * cancellation of the form?
         */
        returnUrl: {
            type: String,
            default: '/',
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
         * Confirm modal's header
         */
        confirmModalHeader: {
            type: String,
            default: 'Confirm achievement(s)',
        },

        /**
         * Confirm modal's meta
         */
        confirmModalMeta: {
            type: String,
            default: 'Awarding the following achievement(s):',
        },

        /**
         * Confirm button's text
         */
        confirmButtonText: {
            type: String,
            default: 'Award selected achievement(s)',
        }
    },
    data() {
        return {
            selectedBadges: [],
            badgeListChangeEvent: BadgeList.constants.EVENT_CHANGE,
        }
    },
    computed: {
        window: () => window,
    },
    methods: {
        /**
         * Callback for when the filters for the badge list form are updated:
         * passed directly to the badge list.
         *
         * Fired from outer space (i.e. from the outside window).
         *
         * @param {object} selectedFilters The filters that were selected
         */
        onFilterChange(selectedFilters) {
            this.$refs.badgeList.onFilterChange(selectedFilters);
        },

        /**
         * Listener for the badge list's change event, emitted when a badge is
         * selected or deselected.
         */
        updateSelectedBadges() {
            this.selectedBadges = this.$refs.badgeList.selectedBadges();
        },

        /**
         * Submit action for this form.
         *
         * Award badges, and then go back to the return URL once done.
         */
        onSubmit() {
            this.toastActivate(this.toastActivateMessage);
            this.$refs.awardModalSubmit.disabled = true;
            let payload = this.awardToSelfOnly ? null : {users: this.users};
            Promise
                .all(this.selectedBadges.map(v => {
                    axios.post(v._links[this.postAwardRouteLookup], payload)
                }))
                .then(() => {
                    this.toastSuccess(this.toastSuccessMessage);
                    this.$refs.awardModal.close();
                    this.window.location.href = this.returnUrl;
                })
                .catch(() => {
                    this.toastError(this.toastFailureMessage);
                })
                .finally(() => {
                    this.$refs.awardModalSubmit.disabled = false;
                });
        },
    },
}
</script>

<template>
    <div>
        <badge-list ref="badgeList"
                    :get-badges-route="getBadgesRoute"
                    :allow-selection="true"
                    :show-badge-menus="false"
                    @[badgeListChangeEvent]="updateSelectedBadges"
        ></badge-list>

        <ul class="flex-list buttons">
            <li>
                <button class="submit full-width"
                        @click.prevent="$refs.awardModal.open"
                        :disabled="selectedBadges.length === 0"
                >{{ confirmButtonText }}</button>
            </li>
            <li><a :href="returnUrl" class="button">Cancel</a></li>
        </ul>

        <reveal-modal ref="awardModal" size="large">
            <h2>{{ confirmModalHeader }}</h2>
            <p class="meta">{{ confirmModalMeta }}</p>
            <ul class="grid achievements-grid medium-block-grid-2 large-block-grid-4">
                <li v-for="badge in selectedBadges">
                    <div class="compact card small-12 badge-card">
                        <div>
                            <badge-image :badge="badge"></badge-image>
                            <h3>{{ badge.name }}</h3>
                        </div>
                    </div>
                </li>
            </ul>

            <template v-if="!awardToSelfOnly">
                <hr />
                <p class="meta">To the following user(s):</p>
                <ul class="grid achievements-grid medium-block-grid-2 large-block-grid-4">
                    <li v-for="user in users">
                        <div class="card small-12 compact">
                            <div>
                                <img :src="user._links.avatar" :alt="user.fullName">
                                <p>{{ user.fullName }}</p>
                            </div>
                        </div>
                    </li>
                </ul>
            </template>

            <ul class="flex-list buttons">
                <li><button ref="awardModalSubmit" @click.prevent="onSubmit" class="submit">Confirm</button></li>
                <li><a @click.prevent="$refs.awardModal.close" class="button">Cancel</a></li>
            </ul>
        </reveal-modal>
    </div>
</template>
