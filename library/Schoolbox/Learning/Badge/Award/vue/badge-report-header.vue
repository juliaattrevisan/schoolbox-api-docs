<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/**
 * The badge report header
 */

export default {
    props: {
        hasSomeStudentsSelected: {
            //To show an 'indeterminate' state on the header checkbox, currently a dash in the box
            type: Boolean,
            required: true,
        },
        areAllStudentsSelected: {
            type: Boolean,
            required: true,
        },
    },

    methods: {
        /**
         * To toggle on/off all students for badge selection
         *
         * @params { Boolean }
         */
        onSelectAllSelected(isChecked) {
            this.$emit("on-select-all-selected", isChecked);
        },
    },
};
</script>

<template>
    <div class="header">
        <div class="header__checkbox">
            <div class="checklist">
                <input
                    id="select-all"
                    type="checkbox"
                    :class="{ indeterminate: hasSomeStudentsSelected }"
                    :checked="areAllStudentsSelected"
                    @change="onSelectAllSelected($event.target.checked)"
                />
                <label for="select-all"></label>
            </div>
        </div>
    </div>
</template>

<style lang="scss" scoped>
.header {
    display: flex;
    width: 100%;
    margin: 1rem 0 0.625rem 0;
    padding: 0 1rem;
}

.student__checkbox {
    width: 50px;
    padding-bottom: 0;
}

.student__name {
    width: 250px;
    padding-bottom: 0;
}

.title {
    font-size: 0.8125rem;
    font-weight: 600;
}
</style>
