<script>

/**
 * Manage your groups
 */

import ManageGroupsItem from './manage-groups-item.vue';


export default {

    components: {
        ManageGroupsItem
    },

    props: {
        groups: {
            type: Array,
            required: true,
        }
    },

    computed: {

        /**
         * Whether to show the empty state
         * 
         * @returns { Number }
         */
        isGroupsEmpty () {
            return this.groups.length === 0;
        }

    },

    mounted () {
        
    },

}

</script>

<template>

   <div>

       <div class="row actions-small-3">
            <div class="list-item">
                <div class="column small-12">
                    <h1>Groups</h1>
                </div>
            </div>
        </div>
        <div class="row">
            <manage-groups-item 
                v-for="group in groups" 
                :key="group.id" 
                :group="group"
            />
        </div>
        
   </div>

</template>

<style scoped>

</style>