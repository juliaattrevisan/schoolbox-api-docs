<script setup>

/**
 * So we can easily filter group members by access type
 */

import { ref, computed, onMounted, nextTick } from 'vue';
import FilterEvents from './manageGroupFilterEvents';


const props = defineProps({
    /**
     * the endpoint to retrieve all the group data from
     */
    selectedFilters: {
        type: Array,
        required: true,
    },
});

const emit = defineEmits(['on-filter-change']);

// onMounted(() => {
//     initResetFiltersListener();
// });
      
const isShowReadAccessChecked = computed(() => {
    return props.selectedFilters.includes('read');
});

const isShowWriteAccessChecked = computed(() => {
    return props.selectedFilters.includes('write');
});

const isShowAdminAccessChecked = computed(() => {
    return props.selectedFilters.includes('admin');
});

/**
 * Reset all the filters back to there base level
 */
// function initResetFiltersListener () {
//     document.addEventListener(FilterEvents.RESET_FILTERS, () => {
//         onShowReadAccessChange();
//         onShowWriteAccessChange();
//         onShowAdminAccessChange();
//     });
// }

/**
 * 
 */
function onFilterChange (accessType) {
    emit('on-filter-change', accessType); //picked up by view-all-files.vue 
}


/**
 * Toggle the display of members that are read acccess
 * If it's checked, it's shown, so there's no filter applied, otherwise any 
 * rows with access 'read' will be hidden
 */
// async function onShowReadAccessChange () {
//     const filterParam = isShowReadAccessChecked.value ? 'read' : ''; 
//     console.warn(filterParam);
//     EventBus.emit(`vue-tables.filter::${FilterEvents.SHOW_READ_ACCESS}`, filterParam); //picked up by manage-group.vue 
//     await nextTick();
//     document.dispatchEvent(new CustomEvent(FilterEvents.SHOW_READ_ACCESS)); //picked up by manage-group.vue 
//     isShowReadAccessChecked.value = !isShowReadAccessChecked.value;
// }

/**
 * Toggle the display of members that are marked as write access
 * If it's checked, it's shown, so there's no filter applied, otherwise any 
 * rows with access 'write' will be hidden
 */
// async function onShowWriteAccessChange () {
//     const filterParam = isShowWriteAccessChecked ? '' : 'write'; 
//     EventBus.emit(`vue-tables.filter::${FilterEvents.SHOW_WRITE_ACCESS}`, filterParam); //picked up by manage-group.vue 
//     await nextTick();
//     document.dispatchEvent(new CustomEvent(FilterEvents.SHOW_WRITE_ACCESS)); //picked up by manage-group.vue 
// }

/**
 * Toggle the display of members that are admin access
 * If it's checked, it's shown, so there's no filter applied, otherwise any 
 * rows with access 'admin' will be hidden
 */
// async function onShowAdminAccessChange () {
//     const filterParam = isShowAdminAccessChecked ? '' : 'admin'; 
//     EventBus.emit(`vue-tables.filter::${FilterEvents.SHOW_ADMIN_ACCESS}`, filterParam); //picked up by manage-group.vue 
//     await nextTick();
//     document.dispatchEvent(new CustomEvent(FilterEvents.SHOW_ADMIN_ACCESS)); //picked up by manage-group.vue 
// }

</script>

<template>
    <div class="filters">
        <div class="filters__column">
            <div class="checklist">
                <label>Access</label>
                <ul class="option-list">
                    <li>
                        <input type="checkbox" id="read-access" :checked="isShowReadAccessChecked" @change="onFilterChange('read')">
                        <label for="read-access">Read</label>
                    </li>
                    <li>
                        <input type="checkbox" id="write-access" :checked="isShowWriteAccessChecked" @change="onFilterChange('write')">
                        <label for="write-access">Write</label>
                    </li>
                    <li>
                        <input type="checkbox" id="admin-access" :checked="isShowAdminAccessChecked" @change="onFilterChange('admin')">
                        <label for="admin-access">Admin</label>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>

<style lang="scss" scoped>

// @import './similarity-report';

.filters {
    background: white;
    display: flex;
    padding: 1rem 0;
    // @media (max-width: $tableToSingleColumn) {
    //     flex-direction: column;
    // }
}

    .filters__column { 
        flex: 1 1 0; //keep both sides equal
        margin: 0 1rem;
    }

legend { 
    margin-bottom: 0.5rem; //extra gap between it and the form element below
}

.option-list li {
    display: inline-block; //rather than block, to line up with similarity score filter better
    margin-right: 2rem;
    // @media (max-width: $tableToSingleColumn) {
    //     display: block;
    // }
}

</style>