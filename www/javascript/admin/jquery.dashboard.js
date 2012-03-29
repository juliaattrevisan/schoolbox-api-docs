dashboardControl = {
	
	init: function(event){
		this.help();
		this.checkForPreselection();
		this.roleSelect();
		this.dragAndDrop();
		this.refreshPalette();
		this.deleteComponent();
	},
	help: function(target) {
		 $j.fn.qtip.defaults.position.corner = {
            target: 'topLeft',
            tooltip: 'bottomLeft'
        }
        $j.fn.qtip.defaults.style = {
            tip: 'bottomLeft',
            name: 'green' 
        }

        $j('h2 span').each(function() {
            activeContent = $j(this).siblings('em').html();
            $j(this).qtip({
                content: activeContent,
                api: {
					// Retrieve the content when tooltip is first rendered
					 onRender: function()
					 {
						var self = this;
					 }
				 }
            });
        });
	},

	checkForPreselection: function() {
		$this = this;
		hashStripped = location.href.split("#")[1];
		if (hashStripped) {
			$j('#group-select').val(hashStripped);
			$j.get('/adminv2/dashboard/get-dashboard/'+hashStripped, function ($layout) {
				$j('#dashboard-layout').fadeOut(function() {
					$j(this).empty().append($layout);
					$j(this).fadeIn();
					$this.dragAndDrop();
				});
			});
		}

	},

	dragAndDrop: function(target) {
		$this = this;

		$j('#dashboard-layout .component-holder').sortable({
			helper: 'clone',
			connectWith: '#dashboard-layout .component-holder',
			update: function (event, ui) {

                var $instanceId;
                if($j(ui.item).attr('id'))
    				$instanceId = $j(ui.item).attr('id').match(/instance\-id\-(\d+)/);
                else
                    $instanceId = null;

				if ( $instanceId != null ) {
					$instanceId = $instanceId[1];
				}

				var $definitionId = $j(ui.item).attr('class').match(/definition\-id\-(\d+)/)[1];

				var $groupId = $j('#group-select').val();

				var $position = $j(ui.item).parent().parent().attr('id').match(/position\-(\w+)/)[1];

				var $sequence = $j(ui.item).index() + 1;

				var $component = {
					definitionId: $definitionId,
					groupId: $groupId,
					position: $position,
					sequence: $sequence
				};

				if ( $instanceId != null ) {
					$component.instanceId = $instanceId;
				}

				$j.post('/adminv2/dashboard/save-instance', $component, function ($instance) {
					$j(ui.item).attr('id', 'instance-id-'+$instance.id);
					$this.refreshPalette();
				});
			},
			placeholder: 'marker',
			items: '.component',
			cursor: 'move',
			stop: function(event, ui) {
				if (!(ui.item.find('.delete-this').length > 0)) {
					ui.item.append('<a href="#" class="delete-this">x</a>')
				}
			}

		});

		$j('#definition-palette .component').draggable({
			helper: 'clone',
			connectToSortable: '#dashboard-layout .component-holder'
		});

		this.refreshPalette();
	},

	refreshPalette: function() {
		$j('#definition-palette .component').each( function () {
			var $definitionElement = $j(this);
			var $definitionIdClass = $definitionElement.attr('class').match(/(definition\-id\-\d+)/)[1];

			if ( !$j('#dashboard-layout .component').hasClass($definitionIdClass) ) {
				$definitionElement
					.draggable('enable')
					.removeClass('disabled');
			}
			else {
				$definitionElement
					.draggable('disable')
					.addClass('disabled');
			}
		} );
	},

	roleSelect: function() {
		$this = this;
		 $j('#group-select').change(function () {
            var $groupId = $j(this).val();
			location.hash = $groupId;

            $j.get('/adminv2/dashboard/get-dashboard/'+$groupId, function ($layout) {
                $j('#dashboard-layout').fadeOut(function() {
                    $j(this).empty().append($layout);
                    $j(this).fadeIn();
                    $this.dragAndDrop();
                });
            });
        });
	},

	deleteComponent: function() {
		$this = this;
		 $j('.delete-this').live('click',function() {
            var $instanceId = $j(this).parent().attr('id').match(/^instance\-id\-(\d+)$/)[1];

            $j(this).parent().slideUp(100,function(){
                $j(this).remove();
                $this.refreshPalette();
            })

            $j.post('/adminv2/dashboard/remove-instance', {instanceId: $instanceId}, function () {

            });

            return false;
        })
	}

}
