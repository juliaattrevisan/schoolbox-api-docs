CountDown = new Class({
		// implement
        Implements: [Options, Events],
        
        // options
        options: {
			h: 0,
			m: 0,
			s: 0
        },
        
        // initialize
        initialize: function(options) {
			// set options
	        this.setOptions(options);
	       	
			// set periodical function
			this.tick.periodical(1000, this);
        },
        
        // decrement ticker
        tick: function() {
        	this.options['s']--;
        	
        	if(this.options['s'] == -1) {
        		this.options['m']--;
        		this.options['s'] = 59;
        	}
        	
        	if(this.options['m'] == -1) {
        		this.options['h']--;
        		this.options['m'] = 59;
        	}
        	
        	if(this.options['h']) {
        		this.text = this.options['h'] + ' : ' + this.options['m'] + ' : ';
        	} else if(this.options['m'])
        		this.text = this.options['m'] + ' : ';
        		
        	this.text += this.options['s'];
        	
        	if(this.options['h'] == -1)
        		this.fireEvent('done');
        	else
        		this.fireEvent('tick');
        }
});