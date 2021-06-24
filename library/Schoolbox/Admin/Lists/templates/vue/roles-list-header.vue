<script>
export default {
    
    props: {
        fields: { //the fields to display within the header
            type: Array,
            required: true
        },
        isSortable: { //allows space for the drag/n drop UI
            type: Boolean,
            required: true
        },
        isDeletable: { //allows space for the delete UI
            type: Boolean,
            required: true
        }
    },

    data () {
        return {
            
        }
    },

    created () {
        // debugger;
    },

    mounted () {
        
    },

    computed: {

        /**
         * Ensure not displaying any fields set to hidden
         * 
         */
        noHiddenFields () {
            return this.fields.filter(field => !field.hidden);
        }

    },

    methods: {
        

    }

}
</script>

<template>
    <div :class="`${ isSortable ? 'sortable' : '' }`">
        <header :class="`list-header actions-small-${ isDeletable ? '1' : '0' }`">
            <div class="list-item">
                <div v-for="(field, index) in noHiddenFields" :key="index" :class="`small-12 medium-${ field.width ? field.width : '2' } columns`">
                    {{ field.caption }}
                    <span v-if="field.tooltip" :class="`hint has-tip tip-${ field.tooltipPosition ? field.tooltipPosition : 'top' }`" data-tooltip :title="field.tooltip">?</span>
                </div>
            </div>
        </header>
    </div>
</template>

<style lang="sass">

</style>