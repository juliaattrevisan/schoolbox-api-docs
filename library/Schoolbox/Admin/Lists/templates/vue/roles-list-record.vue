<script>

/**
 * Display an indivudal record, providing access to edit permissions and delete
 *
 */

export default {
    
    props: {
        displayFields: { //the displayFields to display within the list item
            type: Array,
            required: true
        },
        permissionFields: { //to pass through the permission fields on form submit
            type: Array,
            required: true
        },
        record: { //record to display
            type: Object,
            required: true
        },
        selectedRecord: { //to determine display whether it's the currently selected record
            type: Object,
            required: true
        },
        isPermissionsOpen: { //to change display of row while open
            type: Boolean,
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
            key: -1, //to track the index to use in form submit, which starts at 1 rather than 0
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
         * To loop through those <input hidden> options that won't be shown or used
         * 
         * @returns { Array of Objects }
         */
        permissionOptions () {
            return this.recordOptions.filter((field) => {
                return (field.key !== 'name' && field.key !== 'type' && field.key !== 'id');
            }); 
        },

        /**
         * To vary the display whether selected or not
         * 
         * @returns { Boolean }
         */
        isUnSelectedRecord () {
            return (this.isPermissionsOpen && this.selectedRecord.id !== this.record.id) //is a record selected, and is it this record
        },

        /**
         * Only those permissions currently set to true
         * 
         * @returns { Array of Objects }
         */
        currentPermissionFields () {
            return this.permissionFields.filter((field, index) => this.permissionOptions[index].value); //when the value is set to true, put in the list
        }

    },

    created () {
        this.key = this.$vnode.key + 1;
    },

    // mounted () {
    //     if (this.$vnode.key === 0) debugger; 
    // },

    methods: {
        
        /**
         * Show the permission options
         * 
         */
        onPermissionsSelected () {
            this.$emit('on-permissions-selected', this.record);
        },

        /**
         * To confirm whether should delete the row
         * 
         */
        onDeleteRowSelected () {
            this.$emit('on-delete-row-selected', this.record);
        },

    }

}
</script>

<template>

    <li :data-record-id="record.id" :class="[ isUnSelectedRecord ? 'un-selected-record' : '', 'list-item-container' ]">
        
        <input type="hidden" :name="`id[${key}]`" :value="record.id">
        
        <!-- the checkboxes are selected by #roles-list-configure-access.vue so need to track via hidden vars -->
        <input v-for="(field, index) in permissionOptions" :name="`checkbox-${field.key}[${record.id}]`" type="hidden" :value="`${Number(field.value)}`" :key="index">
       
        <div :class="`actions-small-${ isDeletable ? '1' : '0' }`">
            
            <div class="grip ui-sortable-handle"></div>
            
            <div class="list-item">
                <template v-for="(field, index) in displayFields">
                    <div :class="`small-12 medium-${ field.width ? field.width : '2' } column`" :key="index">
                        <span class="hide-for-medium-up">{{ field.caption }}</span>
                        <template v-if="field.lookup">
                            <select :name="`value-${recordOptions[index].key}[${key}]`" v-model="record[field.key]" :title="recordOptions[index].value">
                                <option v-for="(option, index) in field.lookup" :key="index" :value="option.key">{{ option.value }}</option>
                            </select>
                        </template>
                        <template v-else-if="field.mandatory">
                            <input :name="`mandatory-${recordOptions[index].key}[${key}]`" type="text" :placeholder="field.caption" v-model="record[field.key]" :maxlength="field.maxLength ? field.maxLength : 0">
                        </template>
                        <template v-else>
                            <transition-group name="permissions-list" tag="div" class="permissions-list">
                                <span v-for="field in currentPermissionFields" :key="field.caption" class="permission-list-item" >{{ field.caption }}</span>
                            </transition-group>
                        </template>
                    </div>
                </template>
                <div class="small-12 medium-2 column">
                    <button @click.prevent="onPermissionsSelected" class="configure-permissions-button">Configure Permissions</button>
                </div>
            </div><!-- .list-item -->

            <nav>
                <a @click.prevent="onDeleteRowSelected" class="delete-record-button icon-delete" href="#delete-row" title="Delete Row"></a>
            </nav>

        </div>

    </li>

</template>

<style lang="scss" scoped>

@import 'www/static/css/foundation/utils/_variables.scss';


.list-item-container {
    transition: opacity 0.2s ease-out;
}

.un-selected-record {
    opacity: 0.25;
}

.permissions-list {
    // display: flex;
    // flex-wrap: wrap;
    // align-items: center;
    // gap: 5px;
    width: 100%;
    // min-height: 40px;
    border: 1px solid $form-border-color;
    padding: 2px;
    transition: height 0.3s;
}

.permissions-list-enter, .permissions-list-leave-to {
  opacity: 0;
  transform: translateY(10px);
}
.permissions-list-leave-active {
  position: absolute;
}

.permission-list-item {
    display: inline-block;
    margin: 2px;
    padding: 5px 10px;
    font-size: 0.7rem;
    text-align: center;
    background: $body-bg;
    transition: all 0.3s;
}

.configure-permissions-button {
    width: 100%;
    margin: 0px;
}

.delete-record-button {
    width: 40px;
    height: 40px;
    margin: 14px 8px 0px 0px;
}

</style>