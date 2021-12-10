<script>

/**
 * Manage your groups
 */

import ManageGroupsHeading from './manage-groups-heading.vue';
import ManageGroupsItem from './manage-groups-item.vue';


export default {

    components: {
        ManageGroupsHeading,
        ManageGroupsItem,
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
        <manage-groups-heading />
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