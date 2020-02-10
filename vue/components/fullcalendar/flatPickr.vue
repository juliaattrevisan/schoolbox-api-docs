<template>

</template>

<script>
    import flatpickr from "flatpickr";

    export default {
        name: "flat-pickr",
        props: ['config'],
        data() {
            return {
                selector: this.config.selector,
                activeFlatPickr: {},
                toggle: false,
            }
        },
        components: {flatpickr},
        methods: {
            onChange(selectedDates, dateStr, instance) {
                this.$emit('dateSelected', {
                    selectedDate: dateStr
                });
            },

            onClose() {
                this.toggle = false;
            },

            updateActiveDate(val) {
                this.activeFlatPickr.setDate(val)
            }
        },
        mounted() {
            var self = this;
            // Initialise flatpickr on mount
            self.activeFlatPickr = flatpickr(self.selector, {
                defaultDate: self.config.defaultDate,
                onChange: self.onChange,
                onOpen: self.onOpen,
                onClose: self.onClose,
            });

            document.querySelector(".fc-quickSwitchButton-button").addEventListener('click', function (element) {
                if (self.toggle) {
                    self.activeFlatPickr.close();
                } else {
                    self.toggle = true;
                }
            });
        }
    }
</script>

<style>
    .flatpickr-current-month {
        padding-top: 0 !important;
        top: 0;
    }
</style>
