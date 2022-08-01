<script setup>

/**
 * Shows the page title and to give access to the jelly tot menu,
 * providing various group wide tools
 */

import { onMounted } from 'vue';
import { useStore } from './store/manageGroupStore';
import { useMembersStore } from './store/manageGroupMembersStore';
import { useSettingsStore } from './store/manageGroupSettingsStore';
import { useDisbandStore } from './store/manageGroupDisbandStore';
import Endpoints from './manageGroupEndpoints';


const store = useStore();
const membersStore = useMembersStore();
const settingsStore = useSettingsStore();
const disbandStore = useDisbandStore();

/**
 * ensure the jelly tot menu works
 */
onMounted(() => {
    //
    // $(document).foundation();
});

/**
 * to open the update settings modal
 */
function onUpdateSettingsSelected () {
    settingsStore.promptUpdateSettings();
}

/**
 *
 */
function onEmailGroupSelected () {
    window.location.href = Endpoints.SEND_EMAIL + '?folderId=105';
}

/**
 *
 */
function onEmailGroupParentsSelected () {
    window.location.href = Endpoints.SEND_EMAIL + '?folderId=105&role=parent';
}

/**
 * to prompt to disband the group
 */
function onDisbandSelected () {
    disbandStore.promptDisbandGroup();
}

</script>

<template>
    <div class="row actions-small-1">
        <div class="list-item small-12 columns">
            <h1>Manage Group</h1>
        </div>
        <nav v-if="store.hasLoadedOk">
            <a href="#" class="icon-more" data-dropdown="tab-control" title="Actions"></a>
        </nav>
        <ul id="tab-control" class="f-dropdown" data-dropdown-content>
            <li>
                <a href="#" class="icon-settings" id="updateGroupSettings" @click.prevent="onUpdateSettingsSelected">Update Group Settings</a>
            </li>
            <li v-if="membersStore.hasGroupMembers">
                <a href="#" class="icon-email" id="emailAllGroup" @click.prevent="onEmailGroupSelected">Email Group Members</a>
            </li>
            <li v-if="membersStore.hasGroupMembers">
                <a href="#" class="icon-staff" id="emailAllParents" title="Includes parents of students in the group, as well as parents directly in the group." @click.prevent="onEmailGroupParentsSelected">Email Group Parents</a>
            </li>
            <li>
                <a href="#" class="icon-delete" @click.prevent="onDisbandSelected">Disband Group</a>
            </li>
        </ul>
    </div>
</template>

<style scoped>

</style>
