<script>

/**
 * The list header to display the record titles
 *
 */

import { mapState } from 'pinia';
import { useAdminListStore } from './store/adminListStore'

export default {

    computed: {

        ...mapState(useAdminListStore, {
            isSortable: state => state.loadedData.isSortable, //allows space for the drag/n drop UI
            canDelete: state => state.loadedData.canDelete, //allows space for the delete UI
            headerFields: state => state.headerFields, //allows space for the delete UI
            hasDBid: state => state.hasDBid, //displays the DB id for each list item
        }),

    }

}
</script>

<template>
    <div :class="`${ isSortable ? 'sortable' : '' }`">
        <header :class="`list-header actions-small-${ canDelete ? '1' : '0' }`">
            <div class="list-item">
                <div v-if="hasDBid" class="small-1 column">
                    DB ID
                </div>
                <div v-for="(field, index) in headerFields" :key="index" :class="`small-11 medium-${ field.width ? field.width : '2' } column`">
                    {{ field.caption }}
                    <span v-if="field.tooltip" :class="`hint has-tip tip-${ field.tooltipPosition ? field.tooltipPosition : 'top' }`" data-tooltip :title="field.tooltip">?</span>
                </div>
            </div>
        </header>
    </div>
</template>
