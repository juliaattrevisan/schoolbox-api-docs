<script>
export default {
    
    props: {
        id: { //to track and link to form data
            type: Number, 
            required: true
        },
        displayFields: { //the displayFields to display within the list item
            type: Array,
            required: true
        },
        accessFields: { //to pass through the access fields on form submit
            type: Array,
            required: true
        },
        record: { //record to display
            type: Object,
            required: true
        },
        isSortable: { //whether drag n drop is enabled
            type: Boolean,
            required: true
        },
        isDeletable: { //allows space for the delete UI
            type: Boolean,
            required: true
        },
    },

    data () {
        return {
            currentRoleType: '' //to track whether to display the configure access button if 'staff'
        }
    },

    computed: {

        /**
         * To retrieve the list of key/value pairs contained within the record object
         * 
         * @returns { Array of Objects }
         */
        recordOptions () {
            const recordOptions = [];
            for (const [key, value] of Object.entries(this.record)) {
                recordOptions.push({ key: key, value: value });
            }
            return recordOptions;
        },

        /**
         * To retrieve the list of key/value pairs contained within the record object
         * 
         * @returns { Array of Objects }
         */
        accessOptions () {
            return this.recordOptions.filter((field, index) => index > 2); //all excluding Name and Type
        },

    },

    created () {
        this.currentRoleType = this.recordOptions[1].value;
    },

    mounted () {
        // if (this.$vnode.key === 0) debugger; 
    },

    methods: {
        
        /**
         * Show the access options
         * 
         */
        onConfigureAccessSelected () {
            this.$emit('on-configure-access-selected', this.$vnode.key);
        },

        /**
         * To confirm whether should delete the row
         * 
         */
        onDeleteRowSelected () {
            this.$emit('on-delete-row-selected', this.$vnode.key);
        },

        /**
         * To confirm whether should delete the row
         * 
         */
        onDeleteRowSelected () {
            this.$emit('on-delete-row-selected', this.$vnode.key);
        },

    }

}
</script>

<template>

    <li :data-record-id="id" class="list-item-container">
        
        <input type="hidden" :name="`id[${id}]`" :value="id">
        
        <!-- the checkboxes are selected by #roles-list-configure-access.vue so need to track via hidden vars -->
        <input v-for="(field, index) in accessOptions" :name="`checkbox-${field.key}[${id}]`" type="hidden" :value="`${Number(field.value)}`" :key="index">
       
        <div :class="`actions-small-${ isDeletable ? '1' : '0' }`">
            
            <div class="grip ui-sortable-handle"></div>
            
            <div class="list-item">
                <template v-for="(field, index) in displayFields">
                    <div :class="`small-12 medium-${ field.width ? field.width : '2' } column`" :key="index">
                        <p v-if="field.readonly">{{ recordOptions[index].value }}</p>
                        <label v-else>
                            <span class="hide-for-medium-up">{{ field.caption }}</span>
                            <template v-if="field.lookup">
                                <select :name="`value-${recordOptions[index].key}[${id}]`" v-model="currentRoleType" :title="recordOptions[index].value">
                                    <option v-for="(option, index) in field.lookup" :key="index" :value="option.key">{{ option.value }}</option>
                                </select>
                            </template>
                            <template v-else-if="field.mandatory">
                                <input :name="`mandatory-${recordOptions[index].key}[${id}]`" type="text" :placeholder="field.caption" :value="recordOptions[index].value" :maxlength="field.maxLength ? field.maxLength : 0">
                            </template>
                            <template v-else>
                                <button @click.prevent="onConfigureAccessSelected" class="configure-access-button">Configure access</button>
                            </template>
                        </label>
                    </div>
                </template>
            </div><!-- .list-item -->

            <nav>
                <a @click.prevent="onDeleteRowSelected" class="delete-row-button icon-delete" href="#delete-row" title="Delete Row"></a>
            </nav>

        </div>

    </li>

</template>

<style lang="scss" scoped>

.configure-access-button {
    margin: 0px;
}

.delete-row-button {
    width: 40px;
    height: 40px;
    margin: 14px 8px 0px 0px;
}

</style>