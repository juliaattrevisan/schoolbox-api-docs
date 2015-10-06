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

try {
    socialstreamSession.categoryId;
} catch (e) {
    // define scope
    var socialstreamSession;
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
         * Foundation's reveal modal
         */
        var modal;

		/**
		 * Initialise various configured params and determine a bunch
		 * of ids and stuff that we'll probably use elsewhere
		 *
		 * @param options
		 * @param element
		 * @returns {*}
		 */
		function init(options, element) {
			var i, merged, defaults = {

				/* The title of the browser
				 */
				title: 'Choose a file',

				/* The url to load node contents from
				 */
				url: '/ajax/resource/tree/browse',

				/* The default category to open
				 */
				categoryId: 0,

                /* Should this instance listen to the global rememberer */
                isolate: false,

				/* A callback method. The file you choze from the browser is passed
				 * to this method to deal with in whatever manner you want
				 */
				submitCallback: function(url, filename, metadata) { },

                /* A callback method. When a content ajax-load is successful,
                 * this method will be fired.
                 */
                loadedCallback: function() { },

				/* The element containing the browser
				 */
				element: element,

                /* If the thing should be loaded into a dialog or not
                 */
                modal: true,

                /* An optional mime to be filtering on
                 */
                mimeFilter: null,

                /* selectors */
                browserSelector: '[data-socialstream-browser]',
                breadcrumbSelector: '[data-breadcrumb]',
                categoryNodeSelector: '[data-node-category]',
                fileNodeSelector: '[data-node-file]',
                useFolderSelector: '[data-use-folder]'
			};

			/* Type casting
			 */
			merged = $j.extend(defaults, options);
			if(isNaN(i = parseInt(merged.categoryId, 10))) {
                merged.categoryId = 0;
            } else {
                merged.categoryId = i;
            }

            if (!merged.isolate) {
                try {
                    socialstreamSession.categoryId;
                } catch (e) {
                    socialstreamSession = {categoryId : merged.categoryId};
                }
            }

			return merged;
		}

		/**
		 * Set up modal window
		 */
		function initModal() {
            if (!config.modal) {
                return;
            }

            modal = $j('<div data-reveal class="reveal-modal file-picker" />')
                .append(config.element.children());
            $j('body').append(modal);
            $j(document)
                .foundation('reveal', 'reflow')
                .on('closed.fndtn.reveal', '[data-reveal]', function () {
                    if (modal) {
                        modal.detach();
                        modal = null;
                    }
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
			var args = { relationID: node };

            if (config.mimeFilter === false) {
                // if the filter is explicitly false, exclude all files
                args.mimeFilter = '';
            }
            else if (config.mimeFilter) {
                // if a filter is set, include it
                args.mimeFilter = config.mimeFilter;
            }

            if (!config.isolate) {
                /* Remember where we left off and reopen from here
                 */
                socialstreamSession.categoryId = node;
            }

			/* Load data into the node container
			 */
			config.element.load(config.url, args, function (response, status, xhr) {

                if (xhr.status >= 400) {
                    config.loadedCallback.call($j(this));
                    console.log(response, status, xhr);
                    $j(this).html("<span>"+JSON.parse(response)+"</span>");
                    return;
                }

                config.loadedCallback.call($j(this));

				/* These allow new nodes to be loaded
				 */
				var allNavigators = $j(config.element).find([config.breadcrumbSelector, config.categoryNodeSelector].join(',')),
                    allFiles;

				$j.each(allNavigators, function (index, element) {
					$j(element).find('a').click(function(event) {
                        event.preventDefault();
						loadNode($j(this).attr('rel'));
					});
				});

				/* Assign event handlers to all the files
				 */
				allFiles = $j(config.element).find(config.fileNodeSelector);

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

                        if (config.modal) {
                            modal.foundation('reveal', 'close');
                        }
					});
				});

                /* event handlers for selecting a folder to use
                 * Very similar to file event handler, but filename is contained in a data element instead.
                 */
                $j(config.element).find(config.useFolderSelector).click(function (event) {
                    var url, name, self;

                    event.preventDefault();

                    self = $j(this);
                    url  = self.attr('href');
                    name = self.data('filename_html');

                    config.submitCallback(url, name, undefined);

                    if (config.modal) {
                        modal.foundation('reveal', 'close');
                    }
                });

				/* For all the world to see
				 */
                if (config.modal) {
                    modal.foundation('reveal', 'open');
                }
			});
		}

        config = init(options, this);

        /* Set up modal thingy
         */
        initModal();

        /* Open Pandora's Box
         */
        loadNode(config.isolate ? config.categoryId : socialstreamSession.categoryId);

    };
}(jQuery));