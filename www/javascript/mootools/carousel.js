Carousel = new Class({
		// implement
        Implements: [Options, Events],
        
        // options
        options: {
        	duration: 500,
        },
        
        // initialize
        initialize: function(carousel, options) {
			// set options
	        this.setOptions(options);
	       	
	       	// init elements
	       	this.carousel = $(carousel);
	       	this.items = $$('#' + this.carousel.id + ' li');
	       	this.first = true; this.last = false;
	       	this.current = 1;
	       	
	       	// elements styles
	       	this.carousel.setStyle('width', this.items.length * 100 + '%');
	       	this.items.setStyle('width', 100 / this.items.length + '%');
	              	
	       	// fx
	       	this.fx = new Fx.Tween(this.carousel, {duration: this.options.duration, transition: Fx.Transitions.Quad.easeInOut, unit: '%'});
        },
        
        // move to an item
        move: function(n) {
        	if(1 <= n <= this.items.length) {
        		this.current = n;
        		this.fx.start('margin-left', (n - 1) * -100);
        		
        		if(this.current == 1)
        			this.first = true;
        		else
        			this.first = false;
        			
        		if(this.current == this.items.length)
        			this.last = true;
        		else
        			this.last = false;
        	}
        },
        
        // move to the next item
        next: function() {
			if(this.current < this.items.length) {
				this.move(this.current + 1);
			} else {
				this.move(1);
			}
        },
        
        // move to the next item
        prev: function() {
			if(this.current > 1) {
				this.move(this.current - 1);
			} else {
				this.move(this.items.length);
			}
        },
        
        // to add an item to the end
        create: function() {
        	item = new Element('li');
        
        	// inject
        	item.inject(this.carousel);
        	
        	// restyle
            this.items = $$('#' + this.carousel.id + ' li');
            this.carousel.setStyle('width', this.items.length * 100 + '%');
            this.items.setStyle('width', 100 / this.items.length + '%');
            
            // move
            this.move(this.items.length);
            
            return item;
        },
        
        // repositions an item (n) at pos
        position: function(n, pos) {
    		if(pos == this.items.length) {
    			n.inject(this.items);
    		} else {
    			n.inject(this.items[pos], 'before');
    		}
        },
        
        // to to remove an item
        remove: function(n) {
        	// remove
        	this.items[n].destroy();
        	
        	// restyle
            this.items = $$('#' + this.carousel.id + ' li');
            this.carousel.setStyle('width', this.items.length * 100 + '%');
            this.items.setStyle('width', 100 / this.items.length + '%');
        	
        	// move
            if(this.items[n]) {
            	this.move(n);
            } else {
            	this.move(n - 1);
            }
        }
});