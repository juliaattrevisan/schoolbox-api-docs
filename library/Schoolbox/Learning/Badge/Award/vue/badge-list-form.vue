<script>

import axios from 'axios';
import RevealModal from "@schoolboxVue/components/reveal-modal";
import BadgeImage from '../../vue/badge-image';
import BadgeList from '../../vue/badge-list';
import toastMixin from '@schoolboxVue/components/toast-mixin';
import HorizontalScoller from '@schoolboxVue/components/scroll/horizontal-scoller';
import BottomDrawer from '@schoolboxVue/components/bottomDrawer/bottom-drawer';
import DynamicInput from '@schoolboxVue/components/ckeditor/dynamic-input';
import cdnPrefixMixin from '@schoolboxVue/components/cdn-prefix-mixin';

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
    mixins: [
        cdnPrefixMixin,
        toastMixin
    ],

    components: {
        BadgeList,
        BadgeImage,
        RevealModal,
        HorizontalScoller, //so badges are contained in one line when a lot are selected
        BottomDrawer, //to pop out when +1 badges is selected
        DynamicInput,
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
         * The route which shows the badge create form.
         */
        getBadgeCreateFormRoute: {
            type: String,
            default: '',
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
         * Toast activation message (when only one badge is selected)
         */
        toastActivateMessageSingular: {
            type: String,
            default: 'Awarding achievement...',
        },

        /**
         * Toast success message (when only one badge is selected)
         */
        toastSuccessMessageSingular: {
            type: String,
            default: 'Awarded achievement',
        },

        /**
         * Toast failure message (when only one badge is selected)
         */
        toastFailureMessageSingular: {
            type: String,
            default: 'Awarding achievement failed',
        },

        /**
         * Confirm modal's header (when only one badge is selected)
         */
        confirmModalHeaderSingular: {
            type: String,
            default: 'Confirm achievement',
        },

        /**
         * Confirm modal's badge list meta (when only one badge is selected)
         */
        confirmModalBadgeListMetaSingular: {
            type: String,
            default: 'Awarding the following achievement:',
        },

        /**
         * Confirm modal's user list meta (when only one user is selected)
         */
        confirmModalUserListMetaSingular: {
            type: String,
            default: 'To the following user:',
        },

        /**
         * Confirm button's text (when only one badge is selected)
         */
        confirmButtonTextSingular: {
            type: String,
            default: 'Award selected achievement',
        },

        /**
         * Toast activation message (when more than one badge is selected)
         */
        toastActivateMessagePlural: {
            type: String,
            default: 'Awarding achievements...',
        },

        /**
         * Toast success message (when more than one badge is selected)
         */
        toastSuccessMessagePlural: {
            type: String,
            default: 'Awarded achievements',
        },

        /**
         * Toast failure message (when more than one badge is selected)
         */
        toastFailureMessagePlural: {
            type: String,
            default: 'Awarding achievements failed',
        },

        /**
         * Confirm modal's header (when more than one badge is selected)
         */
        confirmModalHeaderPlural: {
            type: String,
            default: 'Confirm achievements',
        },

        /**
         * Confirm modal's badge list meta (when more than one badge is selected)
         */
        confirmModalBadgeListMetaPlural: {
            type: String,
            default: 'Awarding the following achievements:',
        },

        /**
         * Confirm modal's user list meta (when more than one user is selected)
         */
        confirmModalUserListMetaPlural: {
            type: String,
            default: 'To the following users:',
        },

        /**
         * Confirm button's text (when more than one badge is selected)
         */
        confirmButtonTextPlural: {
            type: String,
            default: 'Award selected achievements',
        },

        /* Used to spawn ckeditor files from cdn somewhere
         */
        siteVersion: {
            type: String
        }
    },
    data() {
        return {
            selectedBadges: [],
            badgeListChangeEvent: 'EVENT_CHANGE',
            hasContentChange: false //to track badge addition changes
        }
    },
    computed: {
        window: () => window,

        // Messages are computed depending on how many badges are selected
        toastActivateMessage() {
            return this.selectedBadges.length === 1
                ? this.toastActivateMessageSingular
                : this.toastActivateMessagePlural;
        },
        toastSuccessMessage() {
            return this.selectedBadges.length === 1
                ? this.toastSuccessMessageSingular
                : this.toastSuccessMessagePlural;
        },
        toastFailureMessage() {
            return this.selectedBadges.length === 1
                ? this.toastFailureMessageSingular
                : this.toastFailureMessagePlural;
        },
        confirmModalHeader() {
            return this.selectedBadges.length === 1
                ? this.confirmModalHeaderSingular
                : this.confirmModalHeaderPlural;
        },
        confirmModalBadgeListMeta() {
            return this.selectedBadges.length === 1
                ? this.confirmModalBadgeListMetaSingular
                : this.confirmModalBadgeListMetaPlural;
        },
        confirmModalUserListMeta() {
            return this.users.length === 1
                ? this.confirmModalUserListMetaSingular
                : this.confirmModalUserListMetaPlural;
        },
        confirmButtonText() {
            return this.selectedBadges.length === 1
                ? this.confirmButtonTextSingular
                : this.confirmButtonTextPlural;
        },
        selectedBadgeCount () {
            return this.selectedBadges.length;
        },
        selectedBadgeText () {
            if (this.selectedBadgeCount > 0) {
                const plural = this.selectedBadgeCount > 1 ? 's' : '';
                const typeSelected = this.confirmButtonText.includes('goal') ? `goal${plural} selected` : `achievement${plural} selected`;
                return `${this.selectedBadgeCount} ${typeSelected}`;
            } else {
                return '';
            }
        }
    },
    watch: {

        /**
         * We need to wait for the transition animation to complete before we re-evaluate
         * the status of horizontal-scroller.
         * @TODO - consider using ResizeObserver once Safari 15 is released, as
         * this could be handled much better by the feature
         */
        selectedBadges (newSelectedBadges, oldSelectedBadges) {
            newSelectedBadges.forEach((badge) => {
                badge.reason = 'Hai';
            });
            if (newSelectedBadges.length !== oldSelectedBadges.length) { //if the number of badges has changed
                setTimeout(() => { //notify horizontal-scroller.vue once enought time to allow the animation to complete has passed
                    this.hasContentChange = true;
                    this.$nextTick(() => this.hasContentChange = false); //set back to false for another possibile filtration
                }, 400);
            }
        }

    },
    mounted () {
        document.addEventListener('badge-list-form__onFilterChange', this.onFilterChange);
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
        onFilterChange(event) {
            this.$refs.badgeList.changeFilters(event.detail.selectedFilters);
        },

        /**
         * When a badge is selected, update the list depending on the selections status
         *
         * @params { Object }
         * @params { Boolean }
         */
        onBadgeSelected (badge, isChecked) {
            if (isChecked) {
                this.selectedBadges = [badge, ...this.selectedBadges]; //add in
            } else {
                this.selectedBadges = this.selectedBadges.filter((selectedBadge) => selectedBadge.id !== badge.id); //or remove
            }
        },

        /**
         * Clear the current badge selection
         */
        onClearSelectedBadges () {
            this.selectedBadges = [];
        },

        /**
         * Submit action for this form.
         *
         * Award badges, and then go back to the return URL once done.
         */
        onSubmit() {
            this.toastActivate(this.toastActivateMessage);
            this.$refs.awardModalSubmit.disabled = true;

            Promise
                .all(this.selectedBadges.map(v => {
                    /* Shank out the ckeditor's contents
                     */
                    let reason = CKEDITOR.instances['reason'].getData();

                    /* Construct each payload for each badge uniquely
                     */
                    let payload = this.awardToSelfOnly ? null : {users: this.users, reason: reason};

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
    <div class="badge-list-form">
        <badge-list
            ref="badgeList"
            :get-badges-route="getBadgesRoute"
            :get-badge-create-form-route="getBadgeCreateFormRoute"
            :allow-selection="true"
            :show-badge-menus="false"
            :selected-badges="selectedBadges"
            @on-badge-selected="onBadgeSelected"
        ></badge-list>

        <ul class="flex-list buttons">
            <li><a :href="returnUrl" class="button">Return to previous page</a></li>
        </ul>

        <bottom-drawer :showDrawer="selectedBadges.length > 0">
            <div class="selected-container">
                <h3 class="selected-container__count"><em>{{ selectedBadgeText }}</em></h3>
                <div class="selected-container__badges">
                    <horizontal-scoller
                        :hasButtons="false"
                        :height="36"
                        :has-content-change="hasContentChange"
                    >
                        <transition-group name="selected-badges" class="selected-badges" tag="div">
                            <span v-for="badge in selectedBadges" :key="badge.id" class="selected-badges__item">
                                <badge-image :badge="badge" class="selected-badge-image"></badge-image>
                            </span>
                        </transition-group>
                    </horizontal-scoller>
                </div>
            </div>
            <ul class="flex-list buttons">
                <li>
                    <button @click.prevent="onClearSelectedBadges" @keyup.enter="onClearSelectedBadges" class="button-clear">Clear selection</button>
                </li>
                <li>
                    <button @click.prevent="$refs.awardModal.open" @keyup.enter="$refs.awardModal.open" class="submit">{{ confirmButtonText }}</button>
                </li>
            </ul>
        </bottom-drawer>

        <reveal-modal ref="awardModal" size="large">
            <h2>{{ confirmModalHeader }}</h2>
            <p class="meta">{{ confirmModalBadgeListMeta }}</p>
            <ul class="grid achievements-grid medium-block-grid-2 large-block-grid-4">
                <li v-for="(badge, index) in selectedBadges" :key="index">
                    <div class="compact card small-12 badge-card">
                        <div>
                            <badge-image :badge="badge"></badge-image>
                            <h3>{{ badge.name }}</h3>
                            <h3>{{ badge.reason }}</h3>
                        </div>
                    </div>
                </li>
            </ul>

            <dynamic-input
                :preset-unique-id="'reason'"
                :inputName="'reason'"
                :value="''"
                :singular="'reason'"
                :allow-edit="true"
                :allow-submit="false"
                :spawn-immediately="false"
                :is-processing="true"
                :cdn-prefix="this.cdnPrefix"
                :site-version="this.siteVersion"
            ></dynamic-input>

            <template v-if="!awardToSelfOnly">
                <hr />
                <p class="meta">{{ confirmModalUserListMeta }}</p>
                <ul class="grid achievements-grid medium-block-grid-2 large-block-grid-4">
                    <li v-for="(user, index) in users" :key="index">
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
                <li><a @click.prevent="$refs.awardModal.close" @keyup.enter="$refs.awardModal.close" class="button">Cancel</a></li>
                <li><button ref="awardModalSubmit" @click.prevent="onSubmit" @keyup.enter="onSubmit" class="submit">Confirm</button></li>
            </ul>
        </reveal-modal>
    </div>
</template>

<style lang="scss" scoped>

.badge-list-form {
    padding-bottom: 100px; // allow space for bottom-drawer at the bottom
}

.selected-container {
    display: flex;
    align-items: baseline;
    height: 50px;
}

    .selected-container__count {
        margin-right: 1rem;
        @media (max-width: 800px) {
            font-size: 0.8rem;
        }
    }

    .selected-container__badges {
        flex: 1 1 auto; //fill up all the space that __count doesn't occupy
        min-width: 50%; //and don't get too small when shrunk down
    }

.selected-badges__item {
    display: inline-block;
    margin: 0px 5px;
    transition: all 0.3s;
}

.selected-badges-enter,
.selected-badges-leave-to {
    opacity: 0;
    transform: translateY(30px);
}

.selected-badges-leave-active {
    position: absolute;
}

</style>