<script>

/**
 * Display an indivudal row, providing access to view and edit permissions and delete
 */

import AdminListRowPopOut from './admin-list-row-pop-out';
import AdminListRowColour from './admin-list-row-colour';

export default {

    components: {
        AdminListRowPopOut,
        AdminListRowColour,
    },

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
         * To vary the display whether selected or not
         *
         * @returns { Boolean }
         */
        isUnselectedRow () {
            return (this.isPopOutOpen && this.selectedRow.id !== this.row.id) //is a row selected, and is it this row if so
        },

    },

    methods: {

        /**
         * Show the pop out fields
         */
        onPopOutSelected (row) {
            this.$emit('on-pop-out-selected', row);
            this.$emit('on-pop-out-name-selected', '');
        },

        /**
         * Higlight the currently selected pop out name
         */
        onPopOutNameSelected (popOutName) {
            this.$emit('on-pop-out-name-selected', popOutName);
        },

        /**
         * To confirm whether should delete the row
         */
        onDeleteRowSelected () {
            this.$emit('on-delete-row-selected', this.row);
            this.$emit('on-pop-out-name-selected', '');
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
                        
                        <template v-if="field.readonly">
                            <p>{{ row[field.id] }}</p>
                        </template>

                        <template v-else-if="field.checkbox">
                            <div class="checklist">
                                <input 
                                    v-model="row[field.id]"
                                    :id="row[field.id]"
                                    type="checkbox"
                                />
                                <label :for="row[field.id]"></label>
                            </div>
                        </template>

                        <template v-else-if="field.lookup">
                            <template v-if="field.lookup.length === 0">
                                <select v-model="row[field.id]" disabled>
                                    <option value="" disabled>None Available</option>
                                </select>
                            </template>
                            <template v-else>
                                <select v-model="row[field.id]">
                                    <template v-if="!field.mandatory">
                                        <option value="">Not Selected</option>
                                    </template>
                                    <option v-for="(option, index) in field.lookup" :key="index" :value="option.key">{{ option.value }}</option>
                                </select>
                            </template>
                        </template>

                        <template v-else-if="field.colour">
                            <admin-list-row-colour
                                :caption="field.caption"
                                :colour="row[field.id]"
                                @input="row[field.id] = $event"
                            />
                        </template>

                        <template v-else-if="field.isPopOutList">
                            <admin-list-row-pop-out
                                :row="row"
                                :pop-out-header-field="popOutHeaderField"
                                :pop-out-fields="popOutFields"
                                :is-pop-out-open="isPopOutOpen"
                                :selected-pop-out-name="selectedPopOutName"
                                @on-pop-out-selected="onPopOutSelected"
                                @on-pop-out-name-selected="onPopOutNameSelected"
                            />
                        </template>

                        <template v-else>
                            <input type="text" :placeholder="field.caption" v-model="row[field.id]" :maxlength="field.maxLength ? field.maxLength : 0">
                        </template>

                        <!-- <template v-if="field.nullable">
                            <div class="checklist">
                                <input 
                                        value="{{ idIsNumeric ? recordIndex - 1 : recordIndex }}"
                                        id="{{ field_key }}-{{ recordIndex }}"
                                        type="checkbox"
                                        {% if record_value is null %}checked{% endif %}
                                        data-nullcheck
                                />
                                <label for="{{ field_key }}-{{ recordIndex }}">
                                    {{- field.nullable_label | default('Null') -}}
                                </label>
                            </div>
                        </template> -->
                        
                    </div>
                </template>
            </div><!-- .list-item -->

            <nav v-if="canDelete">
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

.delete-row-button {
    width: 40px;
    height: 40px;
    margin: 14px 8px 0px 0px;
}

</style>
