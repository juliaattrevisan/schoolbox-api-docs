<script>
export default {
    name: "timetable-calendar-options",

    emits: ["optionSelected"],

    data() {
        return {
            selectedUserOption: null,
        };
    },

    props: {
        options: Object,
    },

    mounted() {
        this.selectedUserOption = this.options["selectedUserOption"];
    },

    methods: {
        /**
         * When a button is clicked:
         *  - Ignore the click if the button is already in the active state
         *  - Update this.selectedUserOption and let Vue update the button states
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
                    this.selectedUserOption = userOption;
                }
            }

            this.$emit("optionSelected", this.selectedUserOption);
        },
    },
};
</script>

<template>
    <div class="row">
        <div class="small-12 columns">
            <dl class="sub-nav">
                <dd
                    v-for="userOption in options['userOptions']"
                    :class="
                        selectedUserOption !== null && userOption.userId === selectedUserOption.userId ? 'active' : ''
                    "
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
