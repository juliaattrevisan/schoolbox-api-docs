<template>
    <form id="search" class="global-search" action="/search" method="GET" v-bind:class="{focus: isFocused}">
        <input id="search-field"
               type="text"
               name="keyword"
               placeholder="Search"
               @focusin="isFocused = true"
               @blur="isFocused = false"
        />

        <label data-jsubmit for="search-field" class="icon-search no-text"></label>
    </form>
</template>

<script>
    export default {
        data: function () {
            return {
                isFocused: false,
            }
        }
    }
</script>

<style scoped>

</style>
