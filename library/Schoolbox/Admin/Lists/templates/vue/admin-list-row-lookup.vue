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
    }

}
</script>

<template>
    <div>
        <template v-if="field.nullable && field.lookup.length === 0">
            <select v-model="row[field.id]" disabled>
                <option value="null">{{ field.nullable_label }}</option>
            </select>
        </template>
        <template v-else-if="field.lookup.length === 0">
            <select v-model="row[field.id]" disabled>
                <option value="">None Available</option>
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