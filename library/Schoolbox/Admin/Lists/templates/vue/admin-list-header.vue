<script>

/**
 * The list header to display the record titles
 *
 */

export default {
    
    props: {
        headerFields: { //the headerFields to display within the header
            type: Array,
            required: true
        },
        isSortable: { //allows space for the drag/n drop UI
            type: Boolean,
            required: true
        },
        isDeletable: { //allows space for the delete UI
            type: Boolean,
            required: true
        }
    },

}
</script>

<template>
    <div :class="`${ isSortable ? 'sortable' : '' }`">
        <header :class="`list-header actions-small-${ isDeletable ? '1' : '0' }`">
            <div class="list-item">
                <div v-for="(field, index) in headerFields" :key="index" :class="`small-12 medium-${ field.width ? field.width : '2' } column`">
                    {{ field.caption }}
                    <span v-if="field.tooltip" :class="`hint has-tip tip-${ field.tooltipPosition ? field.tooltipPosition : 'top' }`" data-tooltip :title="field.tooltip">?</span>
                </div>
            </div>
        </header>
    </div>
</template>

<style lang="sass">

</style>