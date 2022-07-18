<script setup>

/**
 * To select Curriculum codes
 */

import CsSearchInput from './cs-search-input.vue';
import CsModal from './modal/cs-modal.vue';

</script>

<template>
   <div class="row">
        <div class="small-12 columns">
            <label>Curriculum Codes
                <span data-tooltip="" class="hint has-tip tip-top" data-width="200" data-selector="tooltip-l5qfe53bv" aria-describedby="tooltip-l5qfe53bv" title="">?</span>
            </label>
            <cs-search-input />
        </div>
    </div>
    <cs-modal />
</template>

<style scoped>

</style>