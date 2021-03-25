<script>

/**
 * An individual search result that matches the search query with search result
 * highlighted within. Selecting will take the user to relevant page/setting
 *
 */

export default {

  props: {
    result: {
      type: Object,
      required: true,
      default: () => {
        return {
          name: 'name',
          title: 'title',
          description: 'description',
          module: 'module',
          url: 'url'
        }
      }
    },
    currentSearchString: {
      type: String,
      required: true,
      default: ''
    }
  },

  computed: {

    linkUrl() {
      return this.result.url;
    },

    highlightTitleSearch() {
      return this.highlightSearchInElement(this.result.title);
    },

    highlightDescriptionSearch() {
      return this.highlightSearchInElement(this.result.description);
    }

  },

  methods: {

    /**
     * To highlight where the search string matches within the settings result
     *
     * @params {String} the string to find all instanced of the current search string in
     *
     * @returns {String} string for v-html with <span></span> wrapped around each found result
     */
    highlightSearchInElement(toHighlight) {
      return toHighlight?.replace(new RegExp(this.currentSearchString, "gi"), match => {
        return '<mark>' + match + '</mark>';
      });
    }

  }

}

</script>

<template>
  <a class="search-result" :href="linkUrl">
    <h4 class="result-title" v-html="highlightTitleSearch"></h4>
    <p class="result-description" v-html="highlightDescriptionSearch"></p>
  </a>
</template>

<style lang="scss" scoped>

@import 'www/static/css/foundation/utils/_variables.scss';

.search-result {
  display: block;
  margin-top: -1px; //so don't get double borders when stacked
  border: 1px solid #0066b0;
  padding: .75rem;
  background: white;
  transition: back ground 0.2s;

  &:hover {
    background: $content-ui-hover;
  }

  h4 {
    margin-bottom: .5rem;
  }

  p {
    color: #9A9A9A;
    font-size: 13px;
    font-style: italic;
    margin-bottom: 0;
  }
}
</style>
