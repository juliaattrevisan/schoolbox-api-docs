/**
 * Self-contained search object which performs ajax search and remembers what
 * you clicked on etc.
 */
search = {
	url: '/search.php',
	section: '',
	targetDiv: '',
	keyword: '',

	/**
	 * Set up destination divs and stuff
	 *
	 * @param config
	 */
	init: function(config) {
		this.setKeyword(config.keyword);
		this.setSection(config.section);
		this.setPageNumber(config.pageNumber);
		this.setTargetDiv(config.section);
	},

	/**
	 * Load data in
	 */
	load: function() {
		if (!$j(this.getTargetDiv()).hasClass('loaded')) {
			$j(this.getTargetDiv()).load(this.getUrl(), "keyword=" + this.getKeyword() + "&type=" + this.getSection(), function() {
				search.paginate();

				/* Alternating row colours when data is loaded
				 */
				$j('.search-item:nth-child(even)').addClass('dark');
			});

			$j(this.getTargetDiv()).addClass('loaded');
		}
	},

	/**
	 * Add pagination events to all the <a> links
	 */
	paginate: function(){
		$j('div.pagination > a').click(function() {
			search.setPageNumber($j(this).text());

			$j(search.getTargetDiv()).load(search.getUrl(), "keyword=" + search.getKeyword() + "&type=" + search.getSection() + "&page=" + search.getPageNumber(), search.paginate);
		});
	},

	/**
	 * Set the keyword that was searched for
	 */
	setKeyword: function(keyword) {
		this.keyword = keyword;
	},

	/**
	 * Get the keyword that was searched for
	 */
	getKeyword: function() {
		return this.keyword;
	},

	/**
	 * Set the page number that we clicked on
	 *
	 * @param pageNumber
	 */
	setPageNumber: function(pageNumber) {
		this.pageNumber = pageNumber;
	},

	/**
	 * Get the page number that we clicked on
	 */
	getPageNumber: function() {
		return this.pageNumber;
	},

	/**
	 * Hide irrelevant tabs
	 */
	hideTabs: function() {
		$j('div.results > div').hide();
		$j(this.getTargetDiv()).show();
	},

	/**
	 * The url of the backend script that outputs search results
	 */
	getUrl: function() {
		return this.url;
	},

	/**
	 * Set the div where search results get loaded in
	 */
	setTargetDiv: function(title) {
		this.targetDiv = 'div.results > div[title=' + title + ']';
	},

	/**
	 * Get the div where search results get loaded in
	 */
	getTargetDiv: function() {
		return this.targetDiv;
	},

	/**
	 * Set the search type
	 *
	 * This is the type of search you performed (all, users, forum, etc)
	 *
	 * @param section
	 */
	setSection: function(section) {
		this.section = section;
	},

	/**
	 * Get the search type
	 */
	getSection: function() {
		return this.section;
	}
}
