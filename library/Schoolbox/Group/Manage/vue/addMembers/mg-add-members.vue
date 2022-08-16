<script setup>

/**
 * Add members to the group
 */

import { computed } from 'vue';
import { useStore } from '../store/manageGroupStore';
import { useAddMembersStore } from '../store/mgAddMembersStore';
import { useSearchStore } from '../store/mgSearchStore';
import FromTypes from './mgAddMembersFromTypes';
import MgSelectedSearchResults from './mg-selected-search-results.vue';
import MgSearch from './mg-search.vue';


const store = useStore();
const addMembersStore = useAddMembersStore();
const searchStore = useSearchStore();

/**
 * Provide extra details on how the current search selection is to be used
 *
 * @returns { String }
 */
const inputDetails = computed(() => {
    const placeholderOptions = {
        [FromTypes.NAME]: 'You may add users individually by typing their name and selecting from the list.',
        [FromTypes.GROUP]: 'Add all users that belong to another group.',
        [FromTypes.ROLE]: 'Add all users of a particular role in the system.',
        [FromTypes.LIST]: 'Add a list of users (either external user IDs or Schoolbox usernames).',
    }
    return placeholderOptions[addMembersStore.fromType];
});

/**
 * add the selected members to the group
 */
function onAddMembersSelected () {
    addMembersStore.addMembers();
}

/**
 * so we can track the progress of the member access change
 * and update the UI accordingly
 */
addMembersStore.$onAction(({ name, after, onError }) => {
    if (name === 'addMembers') { //as each row is subscribed to onAction, we have to check for the selected row
        store.toastEl.activate('Adding...');
        after(() => {
            store.toastEl.timeout('Members successfully added', true, 5000);
        });
        onError((error) => {
            console.warn(error);
            store.toastEl.timeout('Unable to add members', false, 5000);
        });
    }
});

/**
 * To instantly notify the user that the members access is being updated
 * by updating the button text
 *
 * @returns { String }
 */
const addButtonText = computed(() => addMembersStore.isProcessing ? 'Adding...' : 'Add Members');

</script>

<template>
    <div class="row">
        <div class="small-12 island">
        <h2 class="subheader">Add Members</h2>
            <section>
                <div class="content">
                    <div class="filters">
                        <div class="checklist">
                            <label class="title">From:</label>
                            <ul class="option-list">
                                <li>
                                    <input type="radio" :id="FromTypes.NAME" :value="FromTypes.NAME" v-model="addMembersStore.fromType">
                                    <label for="name">Name</label>
                                </li>
                                <li>
                                    <input type="radio" :id="FromTypes.GROUP" :value="FromTypes.GROUP" v-model="addMembersStore.fromType">
                                    <label for="group">Group</label>
                                </li>
                                <li>
                                    <input type="radio" :id="FromTypes.ROLE" :value="FromTypes.ROLE" v-model="addMembersStore.fromType">
                                    <label for="role">Role</label>
                                </li>
                                <li>
                                    <input type="radio" :id="FromTypes.LIST" :value="FromTypes.LIST" v-model="addMembersStore.fromType">
                                    <label for="list">List</label>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <mg-selected-search-results />
                    <MgSearch />
                    <p class="meta">{{ inputDetails }}</p>
                    <button
                        class="button submit"
                        :disabled="!searchStore.canAddMembers"
                        @click.prevent="onAddMembersSelected">
                        {{ addButtonText }}
                    </button>
                </div>
            </section>
        </div><!-- small-12 island -->
    </div><!-- row  -->
</template>

<style lang="scss" scoped>

.filters {
    background: white;
    display: flex;
    padding: 0.5rem 0 0 0 ;
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

.meta {
    margin-bottom: 1rem;
}

</style>
