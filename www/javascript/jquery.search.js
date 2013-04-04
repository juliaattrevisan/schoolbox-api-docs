/**
 * Self-contained search object which performs ajax search and remembers what
 * you clicked on etc.
 */
search = {

	/* The url that puts out search results
	 */
	url: '/search.php',

	/* The search section (type) that was selected e.g. all, people, etc.
	 */
	section: '',

	/* The actual section's HTML element that was selected
	 */
	sectionElement: '',

	/* The div into which search results get loaded
	 */
	targetDiv: '',

	/* The keyword to search for
	 */
	keyword: '',

	/* A comma-separated string/int tuple, e.g. all,1 / user,1 / user,2
	 *
	 * This is used to parameterise the search method. This interface is now
	 * expected each time a search is run.
	 */
	hash: '',

	/**
	 * Set up destination divs and stuff, storing them in the global search
	 * object literal
	 *
	 * @param config
	 */
	init: function(config) {
		this.setKeyword(config.keyword);
		this.setSection(config.section);
		this.setSectionElement(config.sectionElement);
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

				search.updateStyles();
			});
		}
	},

	/**
	 * Add pagination events to all the <a> links
	 */
	paginate: function(){
		$j('div.pagination > a').bind('click', function(event) {
			event.preventDefault();

			/* Set the page number
			 */
            search.setPageNumber( $j(this).attr('title') );

			/* Update hash configuration
			 */
			search.setHash(search.getSection(), search.getPageNumber());

			/* Slap the hash into the url
			 */
			search.applyHash(search.getHash());

			/* Load search results in, update pagination and styles
			 */
			$j(search.getTargetDiv()).load(search.getUrl(), "keyword=" + search.getKeyword() + "&type=" + search.getSection() + "&page=" + search.getPageNumber(), function() {
				search.paginate();
				search.updateStyles();
			});
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

	/**
	 * Perform a search using the keyword, section and pageNumber to organise
	 * results.
	 *
	 * @param keyword
	 * @param section
	 * @param pageNumber
	 */
	runOnce: function(keyword, section, sectionElement, pageNumber) {
		search.init({
			keyword: keyword,
			section: section,
			sectionElement: sectionElement,
			pageNumber: pageNumber
		});

		/* Set up hash configuration
		 */
		search.setHash(section, pageNumber);
		search.applyHash(search.getHash());

		/* Load result set via ajax into target div
		 */
		search.load();

		/* Add pagination events to links
		 */
		search.paginate();

		/* Hide all divs, and only show the current one
		 */
		search.hideTabs();

        /* Add listeners to any "see all" links present
         */
        search.addSeeMoreListeners();
	},

	/**
	 * Update UI styles
	 *
	 * @param sectionElement
	 */
	updateStyles: function() {
		/* Alternating row colours when data is loaded
		 */
		$j('.search-item:nth-child(even)').addClass('dark');

		/* Remove all selected classes
		 */
		$j('#type a').removeClass('selected');

		/* Select only the current section
		 */
		this.getSectionElement().addClass('selected');

		/* If this was in section "all", then hide pagination. We don't paginate
		 * through this particular section
		 */
		if (this.getSection() == 'all') {
			$j('.pagination').remove();
		}
	},

	/* Getters and Setters
	 * These store/retrieve class variables
	 */

	/**
	 * Set the reference for the section element currently selected
	 *
	 * @param sectionElement
	 */
	setSectionElement: function(sectionElement) {
		this.sectionElement = sectionElement;
	},

	/**
	 * Get the reference for the section element currently selected
	 */
	getSectionElement: function() {
		return this.sectionElement;
	},

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
	},

    /**
     * Add listeners to the "see all" links
     */
    addSeeMoreListeners: function() {

    }
}
