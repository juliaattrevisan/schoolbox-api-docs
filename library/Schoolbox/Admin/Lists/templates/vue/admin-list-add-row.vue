<script>

/**
 * Add a new row to the list
 *
 */

import { mapState } from 'pinia';
import { useAdminListStore } from './store/adminListStore';
import addRow from './mixins/mixinAdminListAddRow';


export default {

    computed: {
        ...mapState(useAdminListStore, ['isProcessing'])
    },

    mixins: [
        addRow
    ],

}

</script>

<template>
    <div class="component-action">
        <section>
            <a @click.prevent="addRow_onNewRowSelected" @keyup.enter="addRow_onNewRowSelected" href="#add-new-row" data-button="add-row" class="button" :disabled="isProcessing">Add New Row</a>
        </section>
    </div>
</template>