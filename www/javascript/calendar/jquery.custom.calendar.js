setupCalendar = {
	calendarFormat: "{SELECTED_DAY}-{SELECTED_MONTH}-{SELECTED_YEAR}",
	calendarImageURL: "/images/core/calendar/calendar-click.png",
	init: function() {
		var $self = this;

        $j.fn.qtip.defaults.position.corner = {
            target: 'topLeft',
            tooltip: 'bottomLeft'
        }

        $j.fn.qtip.defaults.style = {
            tip: 'bottomLeft',
            name: 'green'
        }

        $j('ul.js_happening li.translucent span').qtip({
            content: "This event has yet to reach its publish date and will not be seen by some users."
        });

		$j('.modify a').html('<span></span>Modify');

		$j('.modify a').addClass('btn modify float-left');

		$j('.print-it').click(function() {
			  window.print();
			  return false;
		});

		this.datePicker();
		this.resizeTableCells();
		this.windowResize();
		this.filters.interaction();
	},
	datePicker: function() {
		 $j('.datepicker').datepicker({
			dateFormat: 'dd-mm-yy',
			onSelect: function(dateText, inst) {window.open('/calendar/month/'+dateText, "_self")},
			defaultDate: $j.datepicker.parseDate("dd-mm-yy", this.calendarFormat),
			showOn: 'button',
			buttonImage: this.calendarImageURL,
			buttonImageOnly: true,
			showAnim: 'slideDown'
        });
	},
	resizeTableCells: function() {
		/*
		* This whacky division of cells means that text wrapping is properly
		* dealt with in the older browsers.
		* */
		$j('.js_happening li span.note').each(function() {
			$j(this).css({
				'width': ($j('#calendar').width() - 126) / 7
			});
		})
		$j('.js_happening li span.time').each(function() {
			$j(this).css({
				'width': ($j('#calendar').width() - 250) / 7
			});
		});
	},
	windowResize: function() {
		$j(window).resize(function() {
			if ($j('.cal-bubble').is(':visible')) {
				$j('.cal-bubble').hide();
			};
			setupCalendar.resizeTableCells();
		});
	},
	filters: {

		interaction: function() {

			$j('a.filters').click(function(){
				$j('.filter-drop').toggle();
				$j('.cal-bubble').hide();
				return false;
			});

			$j('.close').click(function(){
				$j('.cal-bubble').hide();
			});

			//make all of the checkboxes checked... darn you firefoxxxxxx
			$j('.filter-list .checkbox').attr('checked', true);

			//run the filter checker to show and hide the items
            $j('.applyFilters').click(function() {
                setupCalendar.filters.organise(["campus", "groups"]);
            });

            $j('#applyFilters').click(function() {
                setupCalendar.filters.organise(["campus", "groups"]);
                $j('.filter-drop').hide();
            });

            $j("body").click(function() {
                $j('.filter-drop').hide();
            });

            $j('.filter-drop').click(function(e) {
                e.stopPropagation();
            });
		},

		organise: function(filterArray) {
			for (i=0; i < filterArray.length; i++ ) {
				$j('.filter-list .' + filterArray[i] + ' input:not(:checked)').each(function(){
					var n = $j(this).attr('value');
					if ($j('ul.js_happening .' + n).is(':visible')) {
					  $j('ul.js_happening .' + n).hide();
					}
				});
				$j('.filter-list .' + filterArray[i] + ' input:checked').each(function(){
					var n = $j(this).attr('value');
					$j('ul.js_happening li.' + n).show();
				});
			}

			$j('.filter-list .events input:not(:checked)').each(function(){
				var n = $j(this).attr('value');
				if ($j('ul.js_happening .' + n).is(':visible')) {
				  $j('ul.js_happening .' + n).hide();
				}
			});
		}
	}
};

