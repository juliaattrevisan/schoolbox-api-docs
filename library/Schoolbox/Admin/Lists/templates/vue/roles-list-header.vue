<script>

/**
 * The list header to display the record titles
 *
 */

export default {
    
    props: {
        headerColumns: { //the headerColumns to display within the header
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
                <div v-for="(column, index) in headerColumns" :key="index" :class="`small-12 medium-${ column.width ? column.width : '2' } columns`">
                    {{ column.caption }}
                    <span v-if="column.tooltip" :class="`hint has-tip tip-${ column.tooltipPosition ? column.tooltipPosition : 'top' }`" data-tooltip :title="column.tooltip">?</span>
                </div>
            </div>
        </header>
    </div>
</template>

<style lang="sass">

</style>