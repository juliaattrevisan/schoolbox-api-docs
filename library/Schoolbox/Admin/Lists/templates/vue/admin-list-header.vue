<script>
/**
 * The list header to display the record titles
 *
 */

import { mapState } from "pinia";
import { useAdminListStore } from "./store/adminListStore";

export default {
    computed: {
        ...mapState(useAdminListStore, {
            isSortable: (state) => state.loadedData.isSortable, //allows space for the drag/n drop UI
            canDelete: (state) => state.loadedData.canDelete, //allows space for the delete UI
            headerFields: (state) => state.headerFields, //allows space for the delete UI
        }),
    },

    mounted() {
        // As vue loads the template after foundation has been initialised, we need to reinitialise the tooltips
        $(document).foundation("tooltip", "reflow");
    },
};
</script>

<template>
    <div :class="`${isSortable ? 'sortable' : ''}`">
        <header :class="`list-header actions-small-${canDelete ? '1' : '0'}`">
            <div class="list-item">
                <label
                    v-for="(field, index) in headerFields"
                    :key="index"
                    :class="`small-12 medium-${field.width ? field.width : '2'} column`"
                >
                    <span :class="`${field.mandatory ? 'required-with-tip' : ''}`">{{ field.caption }}</span>
                    <span
                        v-if="field.tooltip"
                        :class="`hint has-tip tip-${field.tooltipPosition ? field.tooltipPosition : 'left'}`"
                        data-tooltip
                        data-width="300"
                        :title="field.tooltip"
                        >?</span
                    >
                </label>
            </div>
        </header>
    </div>
</template>
