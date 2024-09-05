<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
import flatpickr from "flatpickr";

export default {
    name: "flat-pickr",

    components: { flatpickr },

    props: ["config"],

    data() {
        return {
            selector: this.config.selector,
            activeFlatPickr: {},
            toggle: false,
        };
    },

    mounted() {
        var self = this;
        // Initialise flatpickr on mount
        self.activeFlatPickr = flatpickr(self.selector, {
            defaultDate: self.config.defaultDate,
            onChange: self.onChange,
            onOpen: self.onOpen,
            onClose: self.onClose,
            disableMobile: self.config.disableMobile,
        });

        document.querySelector(".fc-quickSwitchButton-button").addEventListener("click", function (element) {
            if (self.toggle) {
                self.activeFlatPickr.close();
            } else {
                self.toggle = true;
            }
        });
    },

    methods: {
        onChange(selectedDates, dateStr, instance) {
            this.$emit("dateSelected", {
                selectedDate: dateStr,
            });
        },

        onClose() {
            this.toggle = false;
        },

        updateActiveDate(val) {
            this.activeFlatPickr.setDate(val);
        },
    },
};
</script>

<template></template>

<style>
.flatpickr-current-month {
    padding-top: 0 !important;
    top: 0;
}
</style>
