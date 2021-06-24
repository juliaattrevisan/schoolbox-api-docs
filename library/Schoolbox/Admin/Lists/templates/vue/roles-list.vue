<script>

import RolesListHeader from './roles-list-header';
import RolesListItem from './roles-list-item';

export default {
    
    components: {
        RolesListHeader,
        RolesListItem,
    },

    props: {
        tableName: { //the name of the current list, to pass through the form submit
            type: String,
            required: true
        },
        table: { //all the table data, including warning messages, title etc
            type: Object,
            required: true
        },
        initialRecords: { //list of currently active records
            type: Object,
            required: true
        }
    },

    data () {
        return {
            fields: [], //to hold the header fields
            records: [], //to hold initialRecords and manage any further changes
        }
    },

    computed: {

        /**
         * To list of fields that cannot be null
         * 
         */
        fixedList () {
            const fixedList = [];
            for (const [key, value] of Object.entries(this.table.fixed)) {
                fixedList.push({ key: key, value: value });
            }
            return fixedList;
        }

    },

    created () {
        this.fields = Object.values(this.table.fields);
        this.records = Object.values(this.initialRecords);
        
    },

    mounted () {
        
    },

    methods: {



    }

}
</script>

<template>

    <div class="admin-content">
        
        <div class="small-12 island">
            
            <div v-if="records.length === 0" id="listEmptyState" class="empty-state">
                <i class="icon-list"></i>
                <p>The {{ table.title }} list is empty.<br><a href="#" data-button="add-row">Add an item to this list</a>.</p>
            </div>

            <form v-else method="post" action="/adminv2/lists/save">

                <input name="table" type="hidden" :value="tableName" />

                <!-- {# This is used when hidden fields cannot be NULL. Existing records should already be set in DB #} -->
                <input v-for="(field, index) in fixedList" :key="index" type="hidden" :name="`fixed-${field.key}`" :value="field.value" />

                <section id="listEditor">

                    <roles-list-header 
                        :fields="fields"
                        :is-sortable="table.sortable"
                        :is-deletable="table.delete"
                    />

                    <!-- {% set ulAttr = sortable ? 'class="information-list sortable ui-sortable" data-sortable data-list' : 'class="information-list unsortable" data-list' %} -->
                    <ul :class="`information-list ${ table.sortable ? 'sortable ui-sortable' : 'unsortable'}`">
                        <roles-list-item 
                            v-for="(record, index) in records"
                            :key="index"
                            :record="record"
                            :is-sortable="table.sortable"
                        />
                    </ul>

                    <div v-if="!table.noNewRecords" class="component-action">
                        <section>
                            <a href="#" data-button="add-row" class="button">Add New Row</a>
                        </section>
                    </div>

                </section>

                <input type="submit" value="Save"/>

            </form>

        </div><!-- small-12 island -->  

    </div><!-- admin-content -->

</template>

<style lang="sass">

</style>