<script>

/**
 * Manage your groups
 */

import ManageGroupsHeading from './manage-groups-heading';
import ManageGroupsItem from './manage-groups-item';
import Toast from '@vue/components/toast';


export default {

    components: {
        ManageGroupsHeading,
        ManageGroupsItem,
        Toast,
    },

    props: {
        endpoint: { //to join/leave/favourite etc a group
            type: String,
            required: true,
        },
        groups: { //all the groups that the user can access
            type: Array,
            required: true,
        },
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

    methods: { 

        /**
         * 
         * 
         * @param { String }
         */
        onGroupEditStart (text) {
            this.$refs.toast.activate(text);
        },

        /**
         * 
         * 
         * @param { String }
         */
        onGroupEditSuccess (text) {
            this.$refs.toast.timeout(text, true, 3000);
        },

        /**
         * 
         * 
         * @param { String }
         */
        onGroupEditError () {
            this.$refs.toast.timeout(text, false, 3000);
        },

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
                :endpoint="endpoint"
                :group="group"
                @on-edit-start="onGroupEditStart"
                @on-edit-success="onGroupEditSuccess"
                @on-edit-error="onGroupEditError"
            />
        </div>
        <toast ref="toast"></toast>
    </div>
</template>

<style scoped>

</style>