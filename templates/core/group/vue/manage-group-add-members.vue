<script setup>

/**
 * Add members to the group
 */

import { ref, computed, onMounted } from 'vue';
import { useStore } from './store/manageGroupStore';
import { useAddMembersStore } from './store/manageGroupAddMembersStore';


const props = defineProps({
    /**
     * To display the toast access status changes
     */
    toastEl: {
        required: true,
    },
});

const store = useStore();
const addMembersStore = useAddMembersStore();
const isProcessing = ref(false);
const currentFromSelect = ref('name');

/**
 * ensure the tabs and tooltips work
 */
onMounted(() => {
    $(document).foundation(); 
});

/**
 * Update the input place holder text depending on the currentFromSelect
 *
 * @returns { String }
 */
const inputPlaceholder = computed(() => {
    const placeholderOptions = {
        'name': 'Search Users',
        'group': 'Search User Groups',
        'role': 'Search User Roles',
        'list': 'Add from a list',
    }
    return placeholderOptions[currentFromSelect.value];
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
            <section>
                <div class="content">
                    <div class="filters">
                        <div class="checklist">
                            <label class="title">From:</label>
                            <ul class="option-list">
                                <li>
                                    <input type="radio" id="name" value="name" v-model="currentFromSelect">
                                    <label for="name">Name</label>
                                </li>
                                <li>
                                    <input type="radio" id="group" value="group" v-model="currentFromSelect">
                                    <label for="group">Group</label>
                                </li>
                                <li>
                                    <input type="radio" id="role" value="role" v-model="currentFromSelect">
                                    <label for="role">Role</label>
                                </li>
                                <li>
                                    <input type="radio" id="list" value="list" v-model="currentFromSelect">
                                    <label for="list">List</label>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <input
                        type="text"
                        :placeholder="inputPlaceholder"
                        @input="$event.target.composing = false"
                        disabled
                    />
                    <button @click.prevent="onAddMembersSelected" class="button submit" :disabled="store.isProcessing">{{ addButtonText }}</button>
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

</style>