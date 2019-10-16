<template>

</template>

<script>
    import flatpickr from "flatpickr";

    export default {
        name: "flat-pickr",
        props: ['config'],
        data() {
            return {
                selector: this.config.selector
            }
        },
        components: {flatpickr},
        methods: {
            onChange(selectedDates, dateStr, instance) {
                this.$emit('dateSelected', {
                    selectedDate: dateStr
                });
            }
        },
        mounted() {
            // Initialise flatpickr on mount
            flatpickr(this.selector, {
                defaultDate: this.config.defaultDate,
                onChange: this.onChange,
            })
        }
    }
</script>

<style>
    .flatpickr-current-month {
        padding-top: 0 !important;
        top: 0;
    }
</style>

<style lang="scss">
    @import '~flatpickr/dist/flatpickr.min.css';
</style>
