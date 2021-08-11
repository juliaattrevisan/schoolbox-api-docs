<script>

/**
 * The list header to display the record titles
 *
 */

import { mapState, mapGetters } from 'vuex';

export default {

    computed: {

        ...mapState({
            isSortable: state => state.data.isSortable, //allows space for the drag/n drop UI
            canDelete: state => state.data.canDelete, //allows space for the delete UI
        }),
        
        ...mapGetters([
            'headerFields'
        ])
        
    }

}
</script>

<template>
    <div :class="`${ isSortable ? 'sortable' : '' }`">
        <header :class="`list-header actions-small-${ canDelete ? '1' : '0' }`">
            <div class="list-item">
                <div v-for="(field, index) in headerFields" :key="index" :class="`small-12 medium-${ field.width ? field.width : '2' } column`">
                    {{ field.caption }}
                    <span v-if="field.tooltip" :class="`hint has-tip tip-${ field.tooltipPosition ? field.tooltipPosition : 'top' }`" data-tooltip :title="field.tooltip">?</span>
                </div>
            </div>
        </header>
    </div>
</template>