<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/**
 * Groups page title, search and toggles
 */

import selectionTypes from "../js/manageGroupsSelectionTypes";
import enrolmentTypes from "../js/manageGroupsEnrolmentTypes";

export default {
    props: {
        groups: {
            //all the groups that the user can access
            type: Array,
            required: true,
        },
        selectedGroups: {
            //current groups selection
            type: String,
            required: true,
        },
    },

    data() {
        return {
            query: "", //to further refine group search
        };
    },

    computed: {
        /**
         * Change the page title depending on the current group selection
         *
         * @returns { String }
         */
        pageTitle() {
            const pageTitle = {
                [selectionTypes.ALL_GROUPS]: `All groups`,
                [selectionTypes.MY_FAVOURITE_GROUPS]: `My favourite groups`,
                [selectionTypes.GROUPS_IM_IN]: `Groups I'm in`,
                [selectionTypes.GROUPS_I_CAN_JOIN]: `Groups I can join`,
                [selectionTypes.GROUPS_PENDING_MEMBERSHIP]: `Groups pending membership`,
            };
            return pageTitle[this.selectedGroups];
        },

        /**
         * Change the search place holder depending on the current group selection
         *
         * @returns { String }
         */
        inputPlaceholder() {
            const inputPlaceholder = {
                [selectionTypes.ALL_GROUPS]: `Search all groups...`,
                [selectionTypes.MY_FAVOURITE_GROUPS]: `Search my favourite groups...`,
                [selectionTypes.GROUPS_IM_IN]: `Search groups I'm in...`,
                [selectionTypes.GROUPS_I_CAN_JOIN]: `Search groups I can join...`,
                [selectionTypes.GROUPS_PENDING_MEMBERSHIP]: `Search groups pending membership...`,
            };
            return inputPlaceholder[this.selectedGroups];
        },

        /**
         * Grups filtered to the current selection
         *
         * @returns { Array }
         */
        currentGroups() {
            const currentGroups = {
                [selectionTypes.ALL_GROUPS]: () => this.groups,
                [selectionTypes.MY_FAVOURITE_GROUPS]: () =>
                    this.groups.filter(
                        (group) => group.isFavourite && group.enrolmentStatus === enrolmentTypes.ENROLED,
                    ),
                [selectionTypes.GROUPS_IM_IN]: () =>
                    this.groups.filter((group) => group.enrolmentStatus === enrolmentTypes.ENROLED),
                [selectionTypes.GROUPS_I_CAN_JOIN]: () =>
                    this.groups.filter((group) => group.enrolmentStatus === enrolmentTypes.NOT_ENROLED),
                [selectionTypes.GROUPS_PENDING_MEMBERSHIP]: () =>
                    this.groups.filter((group) => group.enrolmentStatus === enrolmentTypes.PENDING),
            };
            return currentGroups[this.selectedGroups]();
        },

        /**
         * Retrieve only the groups that match the search query
         *
         * @returns { Array }
         */
        filteredGroups() {
            if (this.query.length <= 2) {
                return this.currentGroups;
            } else {
                return this.currentGroups.filter((group) =>
                    group.name.toLowerCase().includes(this.query.toLowerCase()),
                );
            }
        },
    },

    watch: {
        /**
         * The <select> is not the only way the selectedGroups can change, it can be toggled by
         * a history state change or for example selection the 'my favourite groups' link in the side menu
         *
         * @param { String }
         */
        selectedGroups(newGroups) {
            this.$refs.select.value = newGroups; //to set <select> value on change
        },

        /**
         * As currentGroups and filteredGroups are not within the <template>
         * we need to watch in order to dispatch changes to manage-groups.vue
         *
         * @param { Array }
         */
        filteredGroups(newGroups) {
            const isSearchActive = this.query.length > 2 ? true : false;
            this.$emit("on-groups-filtered", newGroups, isSearchActive);
        },
    },

    created() {
        this.selectionTypes = selectionTypes; //to access within the template
        this.$emit("on-groups-filtered", this.filteredGroups); //dipsatch to set up the initial group selection on manage-groups.vue
    },

    mounted() {
        this.$refs.select.value = this.selectedGroups; //to set the initial <select> value
    },

    methods: {
        /**
         * Notify manage-groups.vue of state change
         *
         * @param { Object }
         */
        onSelectionChange(event) {
            this.$emit("on-groups-selected", event.currentTarget.value);
        },
    },
};
</script>

<template>
    <div>
        <div class="row">
            <div class="column small-12">
                <transition name="fade" mode="out-in">
                    <h1 :key="pageTitle">{{ pageTitle }}</h1>
                </transition>
            </div>
        </div>
        <div class="row">
            <div class="column small-12 medium-4">
                <select @change="onSelectionChange($event)" ref="select">
                    <option :value="selectionTypes.ALL_GROUPS">All groups</option>
                    <option :value="selectionTypes.MY_FAVOURITE_GROUPS">My favourite groups</option>
                    <option :value="selectionTypes.GROUPS_IM_IN">Groups I'm in</option>
                    <option :value="selectionTypes.GROUPS_I_CAN_JOIN">Groups I can join</option>
                    <option :value="selectionTypes.GROUPS_PENDING_MEMBERSHIP">Groups pending membership</option>
                </select>
            </div>
            <div class="column small-12 medium-8">
                <input
                    v-model="query"
                    :placeholder="inputPlaceholder"
                    type="text"
                    @input="query = $event.target.value /* Fix Android events SA-12170 */"
                />
            </div>
        </div>
    </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s;
}
.fade-enter,
.fade-leave-to {
    opacity: 0;
}
</style>
