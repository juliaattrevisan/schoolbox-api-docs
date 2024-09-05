<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
export default {
    name: "TimetableCalendarOptions",

    props: {
        options: {
            type: Object,
            default() {
                return {};
            },
        },
        selectedOption: {
            type: Object,
            default() {
                return {};
            },
        },
    },

    emits: ["optionSelected"],

    data() {
        return {};
    },

    mounted() {},

    methods: {
        /**
         * When a button is clicked:
         *  - Ignore the click if the button is already in the active state
         *  - Find the userOption that matches the newly selected userId.
         *  - Emit an event to inform timetable-calendar of the new selected option.
         * @param {object} ev - The click event.
         */
        selectOption(ev) {
            const anchor = ev.target;

            if (anchor.parentElement.classList.contains("active")) {
                // This user is already selected, so do nothing.
                return;
            }

            const selectedUserId = Number(anchor.getAttribute("data-userId"));

            for (const userOption of this.options["userOptions"]) {
                if (userOption.userId === selectedUserId) {
                    this.$emit("optionSelected", userOption);
                }
            }
        },
    },
};
</script>

<template>
    <div class="row" v-if="options['userOptions'].length">
        <div class="small-12 columns">
            <dl class="sub-nav">
                <dd
                    v-for="userOption in options['userOptions']"
                    :class="selectedOption !== null && userOption.userId === selectedOption.userId ? 'active' : ''"
                >
                    <a
                        :data-userId="userOption.userId"
                        :title="`'Timetable for ${userOption.formattedName} in ${userOption.firstCampusName}`"
                        @click="selectOption"
                    >
                        {{ userOption.formattedName }} ({{ userOption.firstCampusName }})
                    </a>
                </dd>
            </dl>
        </div>
    </div>
</template>

<style lang="scss"></style>
