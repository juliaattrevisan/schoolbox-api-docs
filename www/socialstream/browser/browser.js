/**
 * socialstreamBrowser
 *
 * This is a plugin which loads nodes in a modal window.
 *
 * This requires the jquery Dialog addon and was based on
 * Doctore's original implementation.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

if (socialstreamSession === undefined) {
    var socialstreamSession = {categoryId : 0};
}

(function($j) {

	/**
	 * Constructor
	 *
	 * @param options
	 */
	$j.fn.socialstreamBrowser = function (options) {

		/* Store the config in this instance so private methods
		 * can access it
		 */
		var config;

		/**
		 * Initialise various configured params and determine a bunch
		 * of ids and stuff that we'll probably use elsewhere
		 *
		 * @param options
		 * @param element
		 * @returns {*}
		 */
		function init(options, element) {
			var merged, defaults = {

				/* The title of the browser
				 */
				title: 'Choose a file',

				/* The url to load node contents from
				 */
				url: '/socialstream/tree.php',

				/* The default category to open
				 */
				categoryId: 0,

				/* A callback method. The file you choze from the browser is passed
				 * to this method to deal with in whatever manner you want
				 */
				submitCallback: function(url, filename, metadata) { },

				/* The element containing the browser
				 */
				element: element,

                /* If the thing should be loaded into a dialog or not
                 */
                modal: true,

                /* An optional mime to be filtering on
                 */
                mimeFilter: null
			};

			/* Type casting
			 */
			merged = $j.extend(defaults, options);
			merged.categoryId = parseInt(merged.categoryId, 10);

			return merged;
		}

		/**
		 * Set up modal window
		 */
		function initModal() {
            if (!config.modal) {
                return;
            }

			$j(config.element).dialog({
				width: 800,
				height: $j(window).height() * 0.75,
				autoOpen: false,
				title: config.title,
				modal: true,
				resizable: false
			});

			 /* Resize dialog proportionally to window
			  */
			$j(window).resize(function (event) {
				$j('.socialstream-browser').height($j(window).height() * 0.75);
			});
		}

		/**
		 * Load a node contents
		 *
		 * @param node
		 */
		function loadNode(node) {

			/* The thing you clicked on
			 */
			var args = {
				relationID: node,
                mimeFilter: config.mimeFilter
			};

			/* Remember where we left off and reopen from here
			 */
			socialstreamSession.categoryId = node;

			/* Load data into the node container
			 */
			config.element.load(config.url, args, function () {

				/* These allow new nodes to be loaded
				 */
				var allNavigators = $j(config.element).find('.breadcrumb, .socialstream-node-category'),
                    allFiles;

				$j.each(allNavigators, function (index, element) {
					$j(element).find('a').click(function(event) {
                        event.preventDefault();
						loadNode($j(this).attr('rel'));
					});
				});

				/* Assign event handlers to all the files
				 */
				allFiles = $j(config.element).find('.socialstream-node-file');

				$j.each(allFiles, function (index, element) {
					$j(element).find('a').click(function (event) {
                        var self, url, name, meta;

                        event.preventDefault();

                        self = $j(this);
						url  = self.attr('href');
						name = self.html();
                        meta = {
                            mime: self.data('mime'),
                            size: self.data('size')
                        };

						/* Chooze and cloze
						 */
						config.submitCallback(url, name, meta);

                        if (this.modal) {
                            $j(config.element).dialog('close');
                        }
					});
				});

                /* event handlers for selecting a folder to use
                 * Very similar to file event handler, but filename is contained in a data element instead.
                 */
                $j(config.element).find('.socialstream-node-use-folder a').click(function (event) {
                    var url, name, self;

                    event.preventDefault();

                    self = $j(this);
                    url  = self.attr('href');
                    name = self.data('filename_html');

                    config.submitCallback(url, name, undefined);

                    if (config.modal) {
                        $j(config.element).dialog('close');
                    }
                });

				/* For all the world to see
				 */
                if (config.modal) {
                    $j(config.element).dialog('open');
                }
			});
		}

        config = init(options, this);

        /* Set up modal thingy
         */
        initModal();

        /* Open Pandora's Box
         */
        loadNode(socialstreamSession.categoryId);

    }
}(jQuery));