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
    	searchStringWordList: {
     		type: Array,
      		required: true,
      		default: []
    	},
		activeIndex: {
     		type: Number,
      		required: true,
      		default: 0
    	}
  	},

	data () {
    	return {
			itemIndex: -1,
			isActive: false
    	}
  	},

  	computed: {

    	highlightTitleSearch () {
      		return this.highlightSearchInElement(this.result.title);
    	},

    	highlightDescriptionSearch () {
      		return this.highlightSearchInElement(this.result.description);
    	}

  	},

	watch: {
		activeIndex () {
			this.checkIndex();
		}
	},

	mounted () {
		this.checkIndex();
	},

	updated () {
		this.checkIndex();
	},

	beforeDestroy () {
		this.checkIndex();
	},


  	methods: {
		
		/**
		 * to highlight the current selection relative to key events
		 * needs to be updated once the animations have completed
		 *
		 */
		checkIndex() {
			this.itemIndex = this.getIndex(this.$el.parentElement);
			if (this.itemIndex === this.activeIndex) {
				this.isActive = true;
			} else {
				this.isActive = false;
			}
		},

		/**
		 * returns an integer indicating the position of the 
		 * passed element relative to the original collection
		 * replacement for $(el).index
		 *
		 * @param el the DOM element to find the index of
		 * 
		 * @return index of the DOM element
		 */
		getIndex(el) {
			if (!el) {
				return -1;
			}
			let i = 0;
			while (el = el.previousElementSibling) {
				i++;
			}
			return i;
		},

		/**
		 * To highlight where the search string matches within the settings result
		 *
		 * @params {String} the string to find all instanced of the current search string in
		 *
		 * @returns {String} string for v-html with <mark></mark> wrapped around each found result
		 */
		highlightSearchInElement(toHighlight) {
			let highlighted = toHighlight;
			this.searchStringWordList.forEach(wordInSearch => {
				highlighted = highlighted.replace(new RegExp(wordInSearch, "gi"), match => {
					return '<mark>' + match + '</mark>';
				});
			});
			return highlighted;
		},

		/**
		 * so mouse selection takes precendence over the arrow/enter selection
		 *
		 */
		mouseOverResult() {
			this.$emit('on-mouse-over-result');
		},

		/**
		 * dispatch to parent so can move to relevant settings page
		 *
		 */
		selectResult() {
			this.$emit('on-selected-result', this.result.url);
		}

  	}

}

</script>

<template>
	<a class="search-result" 
		:href="result.url" 
		:class="{ searchResultActive: isActive }"
		@mouseover="mouseOverResult"
		@click.prevent="selectResult"
	>
    	<h4 class="result-title" v-html="highlightTitleSearch"></h4>
    	<p class="result-description" v-if="result.description" v-html="highlightDescriptionSearch"></p>
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

.searchResultActive {
	background: $content-ui-hover;
}

//::v-deep to target the deep nest
//https://vue-loader.vuejs.org/guide/scoped-css.html#deep-selectors
.result-title ::v-deep .highlight-text {
	background: #ff0;
}
.result-description ::v-deep .highlight-text {
	background: #ff0;
}

</style>
