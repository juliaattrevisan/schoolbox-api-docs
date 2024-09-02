<script>
/**
 * Display an indivudal row, providing access to view and edit permissions and delete
 */

import { mapState, mapActions } from "pinia";
import { useAdminListStore } from "../store/adminListStore";
import AdminListRowLookup from "./admin-list-row-lookup";
import AdminListRowColour from "./admin-list-row-colour";
import AdminListRowPopOut from "./admin-list-row-pop-out";

export default {
    components: {
        AdminListRowLookup,
        AdminListRowColour,
        AdminListRowPopOut,
    },

    props: {
        row: {
            //row to display
            type: Object,
            required: true,
        },
    },

    computed: {
        ...mapState(useAdminListStore, {
            isSortable: (state) => state.loadedData.isSortable,
            canDelete: (state) => state.loadedData.canDelete,
            selectedRow: (state) => state.selectedRow,
            isPopOutOpen: (state) => state.isPopOutOpen,
            selectedPopOutName: (state) => state.selectedPopOutName,
            headerFields: (state) => state.headerFields, //the headerFields to display within the list item
            popOutHeaderField: (state) => state.popOutHeaderField, //the title field of the pop out
            popOutFields: (state) => state.popOutFields, //when a #admin-list-pop-out.vue is in use
        }),

        /**
         * To retrieve a list of key/value pairs contained within the row object
         *
         * @returns { Array of Objects }
         */
        rowKeyValueList() {
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
        isUnselectedRow() {
            return this.isPopOutOpen && this.selectedRow.id !== this.row.id; //is a row selected, and is it this row if so
        },
    },

    methods: {
        ...mapActions(useAdminListStore, ["deleteRowPrompt"]),

        /**
         * To confirm whether should delete the row
         */
        onDeleteRowSelected() {
            this.deleteRowPrompt(this.row);
        },
    },
};
</script>

<template>
    <li :class="[isUnselectedRow ? 'un-selected-row' : '', 'list-item-container']" :data-row-id="row.id">
        <div :class="`actions-small-${canDelete ? '1' : '0'}`">
            <div v-if="isSortable" class="grip ui-sortable-handle"></div>

            <div class="list-item">
                <div
                    v-for="field in headerFields"
                    :class="`small-12 medium-${field.width ? field.width : '2'} column`"
                    :key="field.id"
                >
                    <span class="hide-for-medium-up">{{ field.caption }}</span>

                    <template v-if="field.readonly">
                        <p>{{ row[field.id] }}</p>
                    </template>

                    <template v-else-if="field.checkbox">
                        <div class="checklist">
                            <input :id="`${row.id}-${field.caption}`" type="checkbox" v-model="row[field.id]" />
                            <label :for="`${row.id}-${field.caption}`"></label>
                        </div>
                    </template>

                    <template v-else-if="field.lookup">
                        <admin-list-row-lookup :field="field" :row="row" />
                    </template>

                    <template v-else-if="field.colour">
                        <admin-list-row-colour :field="field" :row="row" :colour="row[field.id]" />
                    </template>

                    <template v-else-if="field.isPopOutList">
                        <admin-list-row-pop-out :row="row" />
                    </template>

                    <template v-else>
                        <input
                            type="text"
                            :required="field.mandatory"
                            :placeholder="field.caption"
                            v-model="row[field.id]"
                            :maxlength="field.maxLength ? field.maxLength : ''"
                        />
                    </template>

                    <template v-if="row.hasValidationError && row.hasValidationError[field.id]">
                        <label class="error">This field is required.</label>
                    </template>
                </div>
            </div>
            <!-- .list-item -->

            <nav v-if="canDelete">
                <a
                    @click.prevent="onDeleteRowSelected"
                    class="delete-row-button icon-delete"
                    href="#delete-row"
                    title="Delete Row"
                ></a>
            </nav>
        </div>
    </li>
</template>

<style lang="scss" scoped>
@import "@css/core/base/_variables.scss";

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
