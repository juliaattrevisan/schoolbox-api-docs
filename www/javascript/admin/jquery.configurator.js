/**
 * @requires setupCustomise (jquery.component.js)
 * @param opts - defaults are for dashboard implementation
 * @return {*} - self
 */
$j.fn.configurator = function(opts) {
    var defaults = {
        context: 'dashboard',
        dropDownSelector: '#group-select',
        disabledMsg: 'This component can only be used once, and is already active for the group selected.'
    };
    var options = $j.extend({}, defaults, opts);
    var configurator = this;
    var dropDownSelector = $j(options.dropDownSelector);

    /* METHODS */
    this.start = function() {
        this.help();
        this.checkForPreselection();
        this.roleSelect();
        this.dragAndDrop();
        this.refreshPalette();
        this.deleteComponent();
        this.setupCustomise();
    };


    this.help = function() {
        $j.fn.qtip.defaults.position.corner = {
            target: 'topLeft',
            tooltip: 'bottomLeft'
        };
        $j.fn.qtip.defaults.style = {
            tip: 'bottomLeft',
            name: 'green'
        };

        $j('h3 span').each(function() {
            var activeContent = $j(this).siblings('em').html();
            $j(this).qtip({
                content: activeContent,
                api: {
                    // Retrieve the content when tooltip is first rendered
                    onRender: function() {
                        // if you want to use this hook for something, feel free
                    }
                }
            });
        });

        // We'll encapsulate our .qtip() call in your .live() handler method
        $j('.disabled').live('mouseover', function(event) {
            // Bind the qTip within the event handler
            $j(this).qtip({
                overwrite: false,
                content: options.disabledMsg,
                show: {
                    event: event.type,
                    ready: true
                }
            }, event); // Pass through our original event to qTip
        });
    };

    this.checkForPreselection = function() {
        var hashStripped = location.href.split("#")[1];
        if (hashStripped) {
            dropDownSelector.val(hashStripped);
            $j.get('/adminv2/component/get/'+options.context+'/'+hashStripped, function ($layout) {
                $j('#'+options.context+'-layout').fadeOut(function() {
                    $j(this).empty().append($layout);
                    $j(this).fadeIn();
                    configurator.dragAndDrop();
                });
            });
        }
    };

    this.dragAndDrop = function() {
        var instID;
        var templID;

        $j('#'+options.context+'-layout .component-holder').sortable({
            helper: 'clone',
            connectWith: '#'+options.context+'-layout .component-holder',
            update: function (event, ui) {

                var instanceID;
                if($j(ui.item).attr('id'))
                    instanceID = $j(ui.item).attr('id').match(/instance\-id\-(\d+)/);
                else
                    instanceID = null;

                if ( instanceID != null ) {
                    instanceID = instanceID[1];
                }

                var definitionID = $j(ui.item).attr('class').match(/definition\-id\-(\d+)/)[1];

                var groupID = dropDownSelector.val();
                templID = groupID;

                var position = $j(ui.item).parent().parent().attr('id').match(/position\-(\w+)/)[1];

                var sequence = $j(ui.item).index() + 1;

                var component = {
                    context: options.context,
                    definitionId: definitionID,
                    targetId: groupID,
                    position: position,
                    sequence: sequence
                };

                console.log(component);

                if ( instanceID != null ) {
                    component.instanceId = instanceID;
                }

                // need async to be false because this call needs to complete before the GET below begins
                $j.ajaxSetup({async: false});
                $j.post('/adminv2/component/save', component, function (instance) {
                    $j(ui.item).attr('id', 'instance-id-'+instance.id);
                    instID = instance.id;
                    console.log(instID);
                    configurator.refreshPalette();
                });
            },
            placeholder: 'marker',
            items: '.component',
            cursor: 'move',
            stop: function(event, ui) {
                if (!(ui.item.find('.delete-this').length > 0)) {
                    // make an ajax call to retrieve the config to be inserted
                    var data = {
                        component: instID,
                        cid: templID
                    };
                    console.log(data);
                    $j.ajax({
                        async: false,
                        url: "/ajax/componentConfig.php",
                        data: data,
                        type: "GET",
                        success: function(data) {
                            ui.item.append(data);
                        },
                        error: function(data) {
                            console.log(data);
                        }
                    });
                }
            },
            cancel: '.nodrag,:input,option'
        });

        $j('#definition-palette .component').draggable({
            helper: 'clone',
            connectToSortable: '#'+options.context+'-layout .component-holder'
        });

        $j('#'+options.context+'-layout .component-holder').sortable('refresh');

        this.refreshPalette();
    };

    this.refreshPalette = function() {

        $j('#definition-palette .component').each( function () {
            var definitionElement = $j(this);
            var definitionIDClass = definitionElement.attr('class').match(/(definition\-id\-\d+)/)[1];

            var search = '#'+options.context+'-layout .component.' + definitionIDClass;

            var singleton = definitionElement.hasClass('single-instance');
            var instantiated = $j(search).length > 0;

            console.log('searching for ' + search + ': ' + $j(search).length + ' found');

            if(definitionIDClass == 'definition-id-29') {
                console.log($j(search));
            }

        /*
            console.log(this);
            console.log('singleton: ' + (singleton ? 'true' : 'false') + ', inst: ' + (instantiated ? 'true' : 'false'));
        */

            if(singleton) {
                if(instantiated) {
                    definitionElement.draggable('disable').addClass('disabled');
                }
                else {
                    definitionElement.draggable('enable').removeClass('disabled');
                }
            }
            else {
                definitionElement.draggable('enable').removeClass('disabled');
            }
        } );
    };

    this.roleSelect = function() {
        dropDownSelector.change(function () {
            var self = $j(this);
            var groupID = self.val();
            location.hash = groupID;

            $j.get('/adminv2/component/get/'+options.context+'/'+groupID, function ($layout) {
                $j('#'+options.context+'-layout').fadeOut(function() {
                    var self = $j(this);
                    self.empty().append($layout);
                    self.fadeIn();
                    configurator.dragAndDrop();
                });
            });
        });
    };

    this.deleteComponent = function() {
        $j('.delete-this').live('click', function() {
            var self = $j(this);
            var component = self.parents('.component').first();
            var $instanceId = component.attr('id').match(/^instance\-id\-(\d+)$/)[1];

            component.slideUp(100, function() {
                component.remove();
                configurator.refreshPalette();
            });

            $j.post('/adminv2/component/remove', { instanceId: $instanceId },
                function () {
                    // hook into the post-success if you want
                }
            );
            return false;
        });
    };

    this.setupCustomise = function() {
        // Set up variables and urls
        setupCustomise.ajaxURL = '/'+options.context+'/configForm.php';
        setupCustomise.key = 'cid';

        // The controls for the individual components ie. edit, delete
        setupCustomise.activeComponents();

        $j('#'+options.context+'-layout form').live('submit', function(event) {
            var self = $j(this);
            var panel = self.parent('.editPanel');
            var component = panel.parent('.component');
            var bgcol = component.css('background-color');
            var postdata = self.serialize();

            // perform the ajax
            $j.ajax({
                async: false,
                url: "/adminv2/component/config",
                data: postdata,
                type: "POST",
                success: function() {
                    component.css({'background-color': '#C1F5C1'});
                    panel.slideUp(300, function() {
                        component.css({'background-color': bgcol});
                    });
                },
                error: function(data) {
                    var reason = data.statusText;
                    var code = data.status;
                    var explanation = data.responseText;

                    var msg = 'Error: ' + reason + ' (' + code + '): ' + explanation;

                    self.append('<span class="errmsg">'+msg+'</span>');
                    component.css({'background-color': '#fdd'});
                    setTimeout(function() {
                        self.find('.errmsg').slideUp(300, function() {
                            $j(this).remove();
                            component.css({'background-color': bgcol});
                        });

                    }, 5000);

                }
            });
            event.preventDefault();
            return false
        });
    };
    return this;
};
