/**
 * Self-contained search object which performs ajax search and remembers what
 * you clicked on etc.
 */
search = {
	url: '/search.php',
	section: '',
	targetDiv: '',
	keyword: '',
	hash: '',

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
		if (!$j(this).hasClass('loaded')) {
			$j(this.getTargetDiv()).load(this.getUrl(), "keyword=" + this.getKeyword() + "&type=" + this.getSection() + "&page=" + search.getPageNumber(), function() {

				/* Flag it as having loaded
				 */
				$j(search.getTargetDiv()).addClass('loaded');

				/* Add pagination again
				 */
				search.paginate();

				/* Alternating row colours when data is loaded
				 */
				$j('.search-item:nth-child(even)').addClass('dark');
			});
		}
	},

	/**
	 * Add pagination events to all the <a> links
	 */
	paginate: function(){
		$j('div.pagination > a').live('click', function(event) {
			event.preventDefault();

			/* Set the page number
			 */
			search.setPageNumber($j(this).text());

			/* Update hash configuration
			 */
			search.setHash(search.getSection(), search.getPageNumber());

			/* Slap the hash into the url
			 */
			search.applyHash(search.getHash());

			$j(search.getTargetDiv()).load(search.getUrl(), "keyword=" + search.getKeyword() + "&type=" + search.getSection() + "&page=" + search.getPageNumber(), search.paginate);
		});
	},

	/**
	 * Determine what the current hash configuration is, in the url. Split it
	 * into its separate components
	 */
	fetchHash: function() {
		return window.location.hash.toString().substring(1, window.location.hash.length).split(',');
	},

	/**
	 * Put a new hash in the url
	 */
	applyHash: function(hash) {
		window.location.hash = hash;
	},

	/**
	 * Hide irrelevant tabs
	 */
	hideTabs: function() {
		$j('div.results > div').hide();
		$j(this.getTargetDiv()).show();
	},

	runOnce: function(keyword, section, pageNumber) {
		search.applyHash(search.getHash());
		search.setTargetDiv(search.getSection());

		search.init({
			keyword: keyword,
			section: $j(section).attr('title'),
			pageNumber: pageNumber
		});

		/* Load result set via ajax into target div
		 */
		search.load();

		/* Add pagination events to links
		 */
		search.paginate();

		/* Hide all divs, and only show the current one
		 */
		search.hideTabs();

		/* Tab styling
		 */
		$j('#type a').removeClass('selected');
		$j(section).addClass('selected');
	},

	/* Getters and Setters
	 * These store/retrieve class variables
	 */

	/**
	 * Set the hash configuration, a comma-separated tuple
	 */
	setHash: function(section, pageNumber) {
		this.hash = section + ',' + pageNumber;
	},

	/**
	 * Get the current hash configuration
	 */
	getHash: function() {
		return this.hash;
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
