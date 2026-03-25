<script setup>
/* eslint-disable @eslint-community/eslint-comments/disable-enable-pair -- see below */
/* eslint-disable @eslint-community/eslint-comments/no-unlimited-disable -- see below */
/* eslint-disable -- Ignore this file for ESLint checks for now (SA-18376) */
/* eslint-enable vue/no-v-html */
/**
 * Shows the page title and to give access to the jelly tot menu,
 * providing various group wide tools
 */

import { useStore } from "./store/manageGroupStore";
import { useMembersStore } from "./store/mgMembersStore";
import { useSettingsStore } from "./store/mgSettingsStore";
import { useDisbandStore } from "./store/mgDisbandStore";
import Endpoints from "./mgEndpoints";

const store = useStore();
const membersStore = useMembersStore();
const settingsStore = useSettingsStore();
const disbandStore = useDisbandStore();

/**
 * listen for hasLoadedOk so we can check whether we need to initiation foundation
 */
window.Vue.watch(
    () => store.hasLoadedOk,
    (hasLoadedOk) => {
        if (hasLoadedOk) {
            initFoundation();
        }
    },
);

/**
 * ensure the tooltips work.
 */
async function initFoundation() {
    await window.Vue.nextTick();
    $(document).foundation();
}

/**
 * to open the update settings modal
 */
function onUpdateSettingsSelected() {
    settingsStore.promptUpdateSettings();
}

/**
 *
 */
function onEmailGroupSelected() {
    window.location.href = `${Endpoints.SEND_EMAIL}?folderId=${store.id}`;
}

/**
 *
 */
function onEmailGroupParentsSelected() {
    window.location.href = `${Endpoints.SEND_EMAIL}?folderId=${store.id}&selectedGroups=parents_of`;
}

/**
 * to prompt to disband the group
 */
function onDisbandSelected() {
    disbandStore.promptDisbandGroup();
}
</script>

<template>
    <div class="row actions-small-1">
        <div class="list-item small-12 columns">
            <h1>Manage Group</h1>
        </div>
        <nav v-if="store.hasLoadedOk && settingsStore.isAccessSaved">
            <sbx-pop-out content-id="tab-control"></sbx-pop-out>
        </nav>
        <ul id="tab-control" class="f-dropdown" data-dropdown-content>
            <li>
                <a
                    href="#update-group-settings"
                    class="sbx-button--has-icon"
                    id="updateGroupSettings"
                    @click.prevent="onUpdateSettingsSelected"
                    ><sbx-icon name="settings" />Update Group Settings</a
                >
            </li>
            <li v-if="store.emailEnabled && membersStore.hasGroupMembers">
                <a
                    href="#email-group-members"
                    class="sbx-button--has-icon"
                    id="emailAllGroup"
                    @click.prevent="onEmailGroupSelected"
                    ><sbx-icon name="email" />Email Group Members</a
                >
            </li>
            <li v-if="store.emailEnabled && membersStore.hasGroupMembers">
                <a
                    href="#email-group-parents"
                    class="sbx-button--has-icon"
                    id="emailAllParents"
                    title="Includes parents of students in the group, as well as parents directly in the group."
                    @click.prevent="onEmailGroupParentsSelected"
                    ><sbx-icon name="staff" />Email Group Parents</a
                >
            </li>
            <li>
                <a href="#disband-group" class="sbx-button--has-icon" @click.prevent="onDisbandSelected"
                    ><sbx-icon name="delete" />Disband Group</a
                >
            </li>
        </ul>
    </div>
</template>

<style scoped></style>
