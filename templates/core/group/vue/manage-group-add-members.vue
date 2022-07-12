<script setup>

/**
 * Add members to the group
 */

import { ref, computed } from 'vue';
import { useStore } from './store/manageGroupStore';
import { useAddMembersStore } from './store/manageGroupAddMembersStore';
import LoadingTable from '@schoolboxVue/components/loading/loading-table';


const props = defineProps({
    /**
     * To display the toast access status changes
     */
    toastEl: {
        required: true,
    },
});

const store = useStore();
const addStore = useAddMembersStore();
const isProcessing = ref(false);

/**
 * add the selected members to the group
 */
function onAddMembersSelected () {
    addStore.addMembers();
}

/**
 * so we can track the progress of the member access change
 * and update the UI accordingly
 */
addStore.$onAction(({ name, after, onError }) => {
    if (name === 'addMembers') { //as each row is subscribed to onAction, we have to check for the selected row
        isProcessing.value = true;
        props.toastEl.activate('Adding...');
        after(() => {
            props.toastEl.timeout('Members successfully added', true, 5000);
            isProcessing.value = false;
        });
        onError((error) => {
            console.warn(error);
            props.toastEl.timeout('Unable to add members', false, 5000);
            isProcessing.value = false;
        });
    }
});

/**
 * To instantly notify the user that the members access is being updated 
 * by updating the button text
 *
 * @returns { String }
 */
const addButtonText = computed(() => isProcessing.value ? 'Adding...' : 'Add Members');

</script>

<template>
    <div class="row">
        <div class="small-12 island">
        <h2 class="subheader">Add Members</h2>
            <section v-if="store.hasGroupMembers">
                <div class="content" id="addMembers">
                    <dl class="tabs" data-tab>
                        <dd class="active"><a href="#userName">From Name</a></dd>
                        <dd><a href="#userGroups">From Group</a></dd>
                        <dd><a href="#userRoles">From Role</a></dd>
                        <dd><a href="#userList">From List</a></dd>
                    </dl>
                    <section>
                        <div class="tabs-content no-margin">
                            <div class="content active" id="userName">
                                <form action="/group/modifyForm.php" method="post" class="no-margin">
                                    <input type="hidden" name="categoryID" value="{CATEGORY_ID}" />
                                    <label for="addUser">
                                        Add Members
                                        <span data-tooltip class="has-tip tip-top hint" title="You may add users individually by typing their name and selecting from the list.">?</span>
                                    </label>
                                    <select id="addUser" name="userID[]" multiple placeholder="Search Users"></select>
                                    <input type="submit" :value="addButtonText" :disabled="store.isProcessing" @click.prevent="onAddMembersSelected" />
                                </form>
                            </div>
                            <div class="content" id="userGroups">
                                <form action="/group/addUserByGroup.php?type=individual&destinationID={CATEGORY_ID}" method="post" class="no-margin">
                                    <input type="hidden" name="individualGroupID" id="individualGroupID" />
                                    <label for="addUsersByIndividualGroup">
                                        Group
                                        <span data-tooltip class="has-tip tip-top hint" title="Add all users that belong to another group.">?</span>
                                    </label>
                                    <input id="addUsersByIndividualGroup" type="text" size="30" placeholder="Search User Groups" />
                                    <input type="submit" :value="addButtonText" :disabled="store.isProcessing" @click.prevent="onAddMembersSelected" />
                                </form>
                            </div>
                            <div class="content" id="userRoles">
                                <form action="/group/addUserByGroup.php?type=system&destinationID={CATEGORY_ID}" method="post" class="no-margin">
                                    <input type="hidden" name="systemGroupID" id="systemGroupID" />
                                    <label for="addUsersBySystemGroup">
                                        Role
                                        <span data-tooltip class="has-tip tip-top hint" title="Add all users of a particular role in the system.">?</span>
                                    </label>
                                    <input id="addUsersBySystemGroup" type="text" size="30" placeholder="Search User Roles" />
                                    <input type="submit" :value="addButtonText" :disabled="store.isProcessing" @click.prevent="onAddMembersSelected" />
                                </form>
                            </div>
                            <div class="content" id="userList">
                                <form action="/group/modifyForm.php" method="post" class="no-margin">
                                    <label for="addUsersByList">
                                        User List
                                        <span data-tooltip class="has-tip tip-top hint" title="Add a list of users (either external user IDs or Schoolbox usernames).">?</span>
                                    </label>
                                    <input type="hidden" name="categoryID" value="{CATEGORY_ID}" />
                                    <textarea name="bulkAdd" id="addUsersByList" cols="30" rows="20"></textarea>
                                    <input type="submit" :value="addButtonText" :disabled="store.isProcessing" @click.prevent="onAddMembersSelected" />
                                </form>
                            </div>
                        </div>
                    </section>
                </div>
            </section>
            <loading-table v-else-if="store.isLoading" />
        </div><!-- small-12 island -->
    </div><!-- row  -->
</template>

<style scoped>
    
</style>