<script>
export default {
    
    props: {
        fields: { //the fields to display within the list item
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
            
        }
    },

    computed: {

        /**
         * To track the list items
         * 
         * @returns { Number }
         */
        id () {
            return this.$vnode.key + 1;
        },

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
        }

    },

    created () {
        
    },

    mounted () {
        // if (this.$vnode.key === 0) debugger; 
    },

    methods: {
        
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
        <div :class="`actions-small-${ isDeletable ? '1' : '0' }`">
            <div class="grip ui-sortable-handle"></div>
            <div class="list-item">
                <template v-for="(field, index) in fields">
                    <div :class="`small-12 medium-${ field.width ? field.width : '2' } column`" :key="index">
                        <p v-if="field.readonly">{{ recordOptions[index].value }}</p>
                        <label v-else>
                            <span class="hide-for-medium-up">{{ field.caption }}</span>
                            <template v-if="field.lookup">
                                <select v-if="field.lookup.length === 0" :name="`value-${recordOptions[index].key}[${$vnode.key}]`" disabled>
                                    <option value="" disabled>None Available</option>
                                </select>  
                                <select v-else :name="`value-${recordOptions[index].key}[${$vnode.key}]`" v-model="recordOptions[index].value" :title="recordOptions[index].value">
                                    <option v-if="!field.mandatory" value="">Not Selected</option>
                                    <option v-for="(option, index) in field.lookup" :key="index" :value="option.key">{{ option.value }}</option>
                                </select>
                            </template>
                            <template v-else-if="field.mandatory">
                                <input :name="`mandatory-${recordOptions[index].key}[${id}]`" type="text" :placeholder="field.caption" :value="recordOptions[index].value" :maxlength="field.maxLength ? field.maxLength : 0">
                            </template>
                        </label>
                    </div>
                </template>
            </div>
            <nav>
                <a @click.prevent="onDeleteRowSelected" class="delete-row icon-delete" href="#delete-row" title="Delete Row"></a>
            </nav>
        </div>
    </li>
</template>

<style lang="scss" scoped>

.delete-row {
    width: 40px;
    height: 40px;
    margin: 14px 8px 0px 0px;
}

</style>