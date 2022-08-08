<script setup>

/**
 * So we can easily filter group members by access type
 */

import { computed } from 'vue';
import filterTypes from './manageGroupAccessTypes';
import { useMembersStore } from './store/manageGroupMembersStore';


const membersStore = useMembersStore(); //to access the selectedFilters and notify of any changes

const isShowWaitingListChecked = computed(() => membersStore.selectedFilters.includes(filterTypes.WAITING));
const isShowReadAccessChecked = computed(() => membersStore.selectedFilters.includes(filterTypes.READ));
const isShowWriteAccessChecked = computed(() => membersStore.selectedFilters.includes(filterTypes.WRITE));
const isShowAdminAccessChecked = computed(() => membersStore.selectedFilters.includes(filterTypes.ADMIN));

/**
 * Dispatch to filter the member display
 *
 * @param { String }
 */
function onFilterChange (accessType) {
    membersStore.updateFilterSelection(accessType);
}

</script>

<template>
    <div class="filters">
        <div class="checklist">
            <label class="title">Filter:</label>
            <ul class="option-list">
                <li>
                    <input type="checkbox" id="waiting-list" :checked="isShowWaitingListChecked" @change="onFilterChange(filterTypes.WAITING)">
                    <label for="waiting-list">Waiting List</label>
                </li>
                <li>
                    <input type="checkbox" id="admin-access" :checked="isShowAdminAccessChecked" @change="onFilterChange(filterTypes.ADMIN)">
                    <label for="admin-access">Group Admin</label>
                </li>
                <li>
                    <input type="checkbox" id="write-access" :checked="isShowWriteAccessChecked" @change="onFilterChange(filterTypes.WRITE)">
                    <label for="write-access">Write Access</label>
                </li>
                <li>
                    <input type="checkbox" id="read-access" :checked="isShowReadAccessChecked" @change="onFilterChange(filterTypes.READ)">
                    <label for="read-access">Read Access</label>
                </li>
            </ul>
        </div>
    </div>
</template>

<style lang="scss" scoped>

.filters {
    background: white;
    display: flex;
    padding: 0 1rem;
}

.title,
.option-list,
.option-list li {
    display: inline-block; //line up along the bottom of the search field input
}

.title,
label {
    font-size: 0.8125rem;
}

.title {
    margin-bottom: 0.5rem;
    margin-right: 1rem;
}

.title,
.option-list {
    @media (max-width: 700px) { //shift things down now as horizontal space is getting tight
        display: block;
    }
}

.option-list li {
    margin-right: 2rem;
    @media (max-width: 700px) { //break the list down into 2 columns to display better on mobile
        display: inline-block;
        margin-right: 0;
        min-width: 110px; //takes us all the way down to 320px devices
    }
}

</style>
