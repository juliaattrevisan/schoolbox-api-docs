<script>

/**
 * Display an indivudal row, providing access to view and edit permissions and delete
 *
 */

export default {
    
    props: {
        headerColumns: { //the headerColumns to display within the list item
            type: Array,
            required: true
        },
        permissionColumns: { //to pass through the permission fields on form submit
            type: Array,
            required: true
        },
        row: { //row to display
            type: Object,
            required: true
        },
        selectedRow: { //to determine display whether it's the currently selected row
            type: Object,
            required: true
        },
        isPermissionsOpen: { //to change display of row while open
            type: Boolean,
            required: true
        },
        selectedPermissionName: { //to show all rows with the same permission
            type: String,
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

    computed: {

        /**
         * To retrieve the list of key/value pairs contained within the row object
         * 
         * @returns { Array of Objects }
         */
        rowFields () {
            const rowFields = [];
            for (const [key, value] of Object.entries(this.row)) {
                rowFields.push({ key: key, value: value });
            }
            return rowFields;
        },

        /**
         * To loop through and add <input hidden> options for permission settings
         * removing those that are added otherwise, or not part of the form data to submit
         * 
         * @returns { Array of Objects }
         */
        permissionFields () {
            return this.rowFields.filter((field) => {
                return (field.key !== 'name' && field.key !== 'type' && field.key !== 'id' && field.key !== 'isNew');
            }); 
        },

        /**
         * To show only those permissions currently set to true
         * 
         * @returns { Array of Objects }
         */
        activePermissionFields () {
            return this.permissionColumns.filter((field, index) => this.permissionFields[index].value); //when the value is set to true, put in the list
        },

        /**
         * To vary the display whether selected or not
         * 
         * @returns { Boolean }
         */
        isUnselectedRow () {
            return (this.isPermissionsOpen && this.selectedRow.id !== this.row.id) //is a row selected, and is it this row if so
        },

        /**
         * For the form variables to reference
         * 
         */
        initialIndex () {
            return this.$vnode.key + 1;
        }

    },

    watch: {

        /**
         * Preset the permission options when setting between staff / all others
         * 
         */
        'row.type': function (newValue) {
            if (newValue === 'staff') { //set all enabled when staff
                this.permissionFields.forEach((option) => {
                    this.row[option.key] = true;
                });
            } else { //otherwise only allowPasswordChange
                this.permissionFields.forEach((option) => {
                    this.row[option.key] = false;
                });
                this.row['canChangePassword'] = true;
            }
        }

    },

    methods: {
        
        /**
         * Show the permission options
         * 
         */
        onPermissionsSelected () {
            this.$emit('on-permissions-selected', this.row);
            this.$emit('on-permission-name-selected', '');
        },

        /**
         * To confirm whether should delete the row
         * 
         */
        onDeleteRowSelected () {
            this.$emit('on-delete-row-selected', this.row);
            this.$emit('on-permission-name-selected', '');
        },

        /**
         * To confirm whether should delete the row
         * 
         */
        onPermissionNameSelected (permissionName) {
            this.$emit('on-permission-name-selected', permissionName);
        }

    }

}
</script>

<template>

    <li :class="[ isUnselectedRow ? 'un-selected-row' : '', 'list-item-container' ]">
        
        <input type="hidden" :name="`id[${initialIndex}]`" :value="row.id">
       
        <div :class="`actions-small-${ isDeletable ? '1' : '0' }`">
            
            <div class="grip ui-sortable-handle"></div>
            
            <div class="list-item">
                <template v-for="(column, index) in headerColumns">
                    <div :class="`small-12 medium-${ column.width ? column.width : '2' } column`" :key="index">
                        <span class="hide-for-medium-up">{{ column.caption }}</span>
                        <template v-if="column.lookup">
                            <select :name="`value-${rowFields[index].key}[${initialIndex}]`" v-model="row[column.key]" :title="rowFields[index].value">
                                <option v-for="(option, index) in column.lookup" :key="index" :value="option.key">{{ option.value }}</option>
                            </select>
                        </template>
                        <template v-else-if="column.mandatory">
                            <input :name="`mandatory-${rowFields[index].key}[${initialIndex}]`" type="text" :placeholder="column.caption" v-model="row[column.key]" :maxlength="column.maxLength ? column.maxLength : 0">
                        </template>
                        <template v-else>
                            <div class="permissions-list-container">
                                <transition-group name="permissions-list" tag="div" class="permissions-list">
                                    <button v-for="field in activePermissionFields" :key="field.caption" :class="[ selectedPermissionName === field.caption ? 'permission-list-item-active' : '', 'permission-list-item']" @click.prevent="onPermissionNameSelected(field.caption)">{{ field.caption }}</button>
                                </transition-group>
                                <button @click.prevent="onPermissionsSelected" class="update-permissions-button">Update Permissions</button>
                            </div>
                        </template>
                    </div>
                </template>
            </div><!-- .list-item -->

            <!-- the checkboxes are selected by #roles-list-configure-access.vue so need to track via hidden vars -->
            <input v-for="(field, index) in permissionFields" :name="`checkbox-${field.key}[${initialIndex}]`" type="hidden" :value="`${Number(field.value)}`" :key="index">

            <nav>
                <a @click.prevent="onDeleteRowSelected" class="delete-row-button icon-delete" href="#delete-row" title="Delete Row"></a>
            </nav>

        </div>

    </li>

</template>

<style lang="scss" scoped>

@import 'www/static/css/foundation/utils/_variables.scss';


.list-item-container {
    transition: opacity 0.3s ease-out;
}

.un-selected-row {
    opacity: 0.2;
}

.permissions-list-container { //to contain the .permissions-list and the .update-permissions-button
    display: flex;
    align-items: stretch;
    width: 100%;
    border: 1px solid $form-border-color;
}

.permissions-list {
    width: 100%;
    padding: 2px;
    transition: height 0.3s;
}

.permissions-list-enter, .permissions-list-leave-to { //to animate on/off when toggling in #roles-list-permissions.vue
  opacity: 0;
  transform: translateY(5px);
}
.permissions-list-leave-active {
  position: absolute;
}

.permission-list-item {
    display: inline-block;
    min-height: auto;
    margin: 2px;
    padding: 5px 10px;
    color: #000;
    font-size: 0.7rem;
    text-align: center;
    background: $body-bg;
    transition: all 0.3s;
    &:hover {
        opacity: 0.8;
    }
}

.permission-list-item-active {
    background: $content-ui-background;
}

.update-permissions-button {
    flex: 0 0 220px;
    margin: 0px;
    padding: 0px;
}

.delete-row-button {
    width: 40px;
    height: 40px;
    margin: 14px 8px 0px 0px;
}

</style>