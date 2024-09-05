<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/**
 * An individual search result that matches the search query with search result
 * highlighted within. Selecting will take the user to relevant page/setting
 *
 */

import ShowMatches from "@schoolboxVue/components/search/show-matches.vue";
import { getIndex } from "@js/util/domUtils";
import { escapeRegExp } from "@js/util/jsUtils";

export default {
    components: {
        ShowMatches,
    },
    props: {
        result: {
            type: Object,
            required: true,
            default: () => {
                return {
                    name: "name",
                    title: "title",
                    description: "description",
                    module: "module",
                    url: "url",
                };
            },
        },
        query: {
            // the current search query
            type: String,
            required: true,
            default: "",
        },
        activeIndex: {
            //to know whether this result is currently active
            type: Number,
            required: true,
            default: 0,
        },
    },

    data() {
        return {
            itemIndex: -1,
            isActive: false,
        };
    },

    watch: {
        activeIndex() {
            this.checkIndex();
        },
    },

    mounted() {
        this.checkIndex();
    },

    updated() {
        this.checkIndex();
    },

    beforeUnmount() {
        this.checkIndex();
    },

    methods: {
        /**
         * to highlight the current selection relative to key events
         * needs to be updated once the animations have completed
         */
        checkIndex() {
            this.itemIndex = getIndex(this.$el.parentElement);
            if (this.itemIndex === this.activeIndex) {
                this.isActive = true;
            } else {
                this.isActive = false;
            }
        },

        /**
         * so mouse selection takes precendence over the arrow/enter selection
         */
        mouseOverResult() {
            this.$emit("on-mouse-over-result");
        },

        /**
         * dispatch to parent so can move to relevant settings page
         */
        selectResult() {
            this.$emit("on-selected-result", this.result.url);
        },
    },
};
</script>

<template>
    <a
        class="search-result"
        :href="result.url"
        :class="{ searchResultActive: isActive }"
        @mouseover="mouseOverResult"
        @focus="mouseOverResult"
        @click.prevent="selectResult"
    >
        <show-matches tag="h4" class="result-title" :text="result.title" :query="query" />
        <show-matches
            v-if="result.description"
            tag="p"
            class="result-description"
            :text="result.description"
            :query="query"
        />
    </a>
</template>

<style lang="scss">
@import "@css/core/base/_variables.scss";

.search-result {
    display: block;
    padding: 0.75rem;
    background: white;
    transition: background 0.2s;
    &:hover,
    &:focus {
        background: $content-ui-hover;
    }
    h4 {
        margin-bottom: 0.5rem;
    }
    p {
        color: #9a9a9a;
        font-size: 13px;
        font-style: italic;
        margin-bottom: 0;
    }
}

.searchResultActive {
    background: $content-ui-hover;
}
</style>
