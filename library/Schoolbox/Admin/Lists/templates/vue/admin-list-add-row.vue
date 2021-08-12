<script>

/**
 * Add a new row to the list
 *
 */

import { mapState } from 'vuex';
import mixinAddRow from './mixins/mixinAdminListAddRow';

export default {

    computed: {
        ...mapState({
            isProcessing: state => state.isProcessing,
        })
    },

    mixins: [
        mixinAddRow
    ],

}

</script>

<template>
    <div class="component-action">
        <section>
            <a @click.prevent="$_mixinAddRow_onNewRowSelected" href="#add-new-row" data-button="add-row" class="button" :disabled="isProcessing">Add New Row</a>
        </section>
    </div>
</template>