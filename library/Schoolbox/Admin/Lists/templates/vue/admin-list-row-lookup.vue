<script>

/**
 * Select option, also ensuring respects nullable option
 */

export default {

    props: {
        field: { //field the lookup is linked to
            type: Object,
            required: true
        },
        row: { //the data for the lookup
            type: Object,
            required: true
        },
    },

    computed: {

        /**
         * Can be disbled based on if there's a field.nullable
         *
         * @returns { Boolean }
         */
        isDisabled () {
            return true;
        }

    }

}
</script>

<template>
    <div>
        <template v-if="field.lookup.length === 0">
            <select v-model="row[field.id]" :disabled="isDisabled">
                <option value="" disabled>None Available</option>
            </select>
        </template>
        <template v-else>
            <select v-model="row[field.id]">
                <template v-if="!field.mandatory">
                    <option value="null">Not Selected</option>
                </template>
                <option v-for="(option, index) in field.lookup" :key="index" :value="option.key">{{ option.value }}</option>
            </select>
        </template>
    </div>
</template>