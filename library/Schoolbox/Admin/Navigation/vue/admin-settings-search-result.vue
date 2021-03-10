<script>


export default {

    props: {
		result: {
			type: Object,
			required: true,
            default: () => {
                return { 
                    title: 'title',
                    name: 'name',
                    module: 'module',
                    description: 'description',
                    url: 'description'
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

        highlightTitleSearch () {
			return this.highlightSearchInElement(this.result.title);
		},

		highlightNameSearch () {
			return this.highlightSearchInElement(this.result.name);
		},

		highlightDescriptionSearch () {
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
		highlightSearchInElement (toHighlight) {
			return toHighlight.replace(new RegExp(this.currentSearchString, "gi"), match => {
				return '<span class="highlight-text">' + match + '</span>';
			});
		}

    }

}

</script>

<template>
    <a class="search-result" :href="result.url">
        <!-- <h1 class="result-title" v-html="highlightTitleSearch"></h1> -->
        <h2 class="result-name" v-html="highlightNameSearch"></h2>
        <p class="result-description" v-html="highlightDescriptionSearch"></p>
    </a>
</template>

<style lang="scss" scoped>

.search-result {
    display: block;
    margin-top: -1px;
    border: 1px solid black;
    padding: 10px;
}

//::v-deep to target the deep nest
//https://vue-loader.vuejs.org/guide/scoped-css.html#deep-selectors
.result-title ::v-deep .highlight-text {
	background: red;
}
.result-name ::v-deep .highlight-text {
	background: red;
}
.result-description ::v-deep .highlight-text {
	background: red;
}

</style>