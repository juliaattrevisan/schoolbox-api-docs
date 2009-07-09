InlineEdit = new Class({
		// implement
        Implements: [Options, Events],
        
        // options
        options: {
        	
        },
        
        // initialize
        initialize: function(element, options) {
			// set options & globals
	        this.setOptions(options);
	       	this.element = $(element);
	       	
	       	// setup event
	       	this.element.addEvent('click', this.edit.bind(this));
        },
        
        // called when the element is clicked
        edit: function() {
        	if($defined(this.input))
        		return false;
        
        	// create element
        	this.input = new Element('input', {value: this.element.get('text')});
        	
        	// replace text with injection
        	this.element.erase('text');
        	this.input.inject(this.element);
        	this.input.focus();
        	
        	// events
        	this.input.addEvent('blur', this.done.bind(this));
        	this.input.addEvent('keydown', function(event) {
        		if(event.key == 'enter' || event.key == 'esc') this.done();
        	}.bind(this));
        },
        
        // called when the input looses focus
        done: function() {
        	// set the text of the element
        	this.element.set('text', this.input.get('value'));
        	
        	// destroy the input
        	this.input = this.input.destroy();
        }
});