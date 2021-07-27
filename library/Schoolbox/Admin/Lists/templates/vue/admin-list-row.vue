<script>

/**
 * Display an indivudal row, providing access to view and edit permissions and delete
 *
 */

export default {

    props: {
        headerFields: { //the headerFields to display within the list item
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
        isSortable: { //whether drag n drop is enabled
            type: Boolean,
            required: true
        },
        canDelete: { //allows space for the delete UI
            type: Boolean,
            required: true
        },
        popOutHeaderField: { //the title of the pop out
            type: Object,
            required: false
        },
        popOutFields: { //when a #admin-list-pop-out.vue is in use
            type: Array,
            required: false    
        },
        isPopOutOpen: { //to change display of row while open
            type: Boolean,
            required: false
        },
        selectedPopOutName: { //to show all rows with the same permission
            type: String,
            required: false
        },
    },

    computed: {

        /**
         * To retrieve a list of key/value pairs contained within the row object
         *
         * @returns { Array of Objects }
         */
        rowKeyValueList () {
            const rowKeyValueList = [];
            for (const [key, value] of Object.entries(this.row)) {
                rowKeyValueList.push({ key: key, value: value });
            }
            return rowKeyValueList;
        },

        /**
         * To show only those options in pop out list currently set to true
         *
         * @returns { Array of Objects }
         */
        activePopOutFields () {
            const fieldIdList = this.popOutFields.map((field) => field.id); //a list of just field.id to get the relevant key/value in rowKeyValueList
            const popOutKeyValueList = this.rowKeyValueList.filter((row) => fieldIdList.some((fieldId) => fieldId === row.key)); //pull out those rowKeyValue object pairs that are part of the pop out 
            return this.popOutFields.filter((field, index) => popOutKeyValueList[index].value); //when the value is set to true, put in the list
        },

        /**
         * To vary the display whether selected or not
         *
         * @returns { Boolean }
         */
        isUnselectedRow () {
            return (this.isPopOutOpen && this.selectedRow.id !== this.row.id) //is a row selected, and is it this row if so
        },

    },

    watch: {

        /**
         * Preset the pop out fields when setting between staff / all others
         *
         */
        'row.type': function (newValue) {
            if (newValue === 'staff') { //set all enabled when staff
                this.popOutFields.forEach((option) => {
                    this.row[option.id] = true;
                });
            } else { //otherwise only allowPasswordChange
                this.popOutFields.forEach((option) => {
                    this.row[option.id] = false;
                });
                this.row['canChangePassword'] = true;
            }
        }

    },

    methods: {

        /**
         * Show the pop out fields
         *
         */
        onPopOutSelected () {
            this.$emit('on-pop-out-selected', this.row);
            this.$emit('on-pop-out-name-selected', '');
        },

        /**
         * To confirm whether should delete the row
         *
         */
        onDeleteRowSelected () {
            this.$emit('on-delete-row-selected', this.row);
            this.$emit('on-pop-out-name-selected', '');
        },

        /**
         * Higlight the currently selected pop out name
         *
         */
        onPopOutNameSelected (popOutName) {
            this.$emit('on-pop-out-name-selected', popOutName);
        }

    }

}
</script>

<template>

    <li :class="[ isUnselectedRow ? 'un-selected-row' : '', 'list-item-container' ]" :data-row-id="row.id">

        <div :class="`actions-small-${ canDelete ? '1' : '0' }`">

            <div v-if="isSortable" class="grip ui-sortable-handle"></div>

            <div class="list-item">
                <template v-for="(field, index) in headerFields">
                    <div :class="`small-12 medium-${ field.width ? field.width : '2' } column`" :key="index">
                        <span class="hide-for-medium-up">{{ field.caption }}</span>
                        <template v-if="field.isPopOutList">
                            <div class="pop-out-list-container">
                                <transition-group name="pop-out-list" tag="div" class="pop-out-list">
                                    <button v-for="field in activePopOutFields" :key="field.caption" :class="[ selectedPopOutName === field.caption ? 'pop-out-list-item-active' : '', 'pop-out-list-item']" @click.prevent="onPopOutNameSelected(field.caption)">{{ field.caption }}</button>
                                </transition-group>
                                <button @click.prevent="onPopOutSelected" class="update-pop-out-button button icon-modify" title="Modify"></button>
                            </div>
                        </template>
                        <template v-else-if="field.lookup">
                            <select v-model="row[field.id]" :title="rowKeyValueList[index].value">
                                <option v-for="(option, index) in field.lookup" :key="index" :value="option.key">{{ option.value }}</option>
                            </select>
                        </template>
                        <template v-else>
                            <input type="text" :placeholder="field.caption" v-model="row[field.id]" :maxlength="field.maxLength ? field.maxLength : 0">
                        </template>
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

@import 'www/static/css/foundation/utils/_variables.scss';

.field {
    @media screen and (max-width: 1500px) {
        padding: 0px 0.5rem;
    }
}

.list-item-container {
    transition: opacity 0.3s ease-out;
}

.un-selected-row {
    opacity: 0.2;
}

.pop-out-list-container { //to contain the .pop-out-list and the .update-pop-out-button
    display: flex;
    align-items: stretch;
    width: 100%;
    border: 1px solid $form-border-color;
}

.pop-out-list {
    width: 100%;
    padding: 2px;
    transition: height 0.3s;
}

.pop-out-list-enter, .pop-out-list-leave-to { //to animate on/off when toggling in #roles-list-permissions.vue
    opacity: 0;
    transform: translateY(5px);
}

.pop-out-list-leave-active {
    position: absolute;
}

.pop-out-list-item {
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

.pop-out-list-item-active {
    background: $content-ui-background;
}

.update-pop-out-button {
    flex: 0 0 40px;
    margin: 0px;
    padding: 0.5rem;
    &:before { //to fix weird button wiggle on press otherwise
        float: none;
        padding: 0;
    }
}

.delete-row-button {
    width: 40px;
    height: 40px;
    margin: 14px 8px 0px 0px;
}

</style>
