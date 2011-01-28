/* Used in template file addMultiFile.htm for allowing the user to upload multiple files */

var MultiSelector = function(){
	// Last file and description
	var file;
	var description;
	
	// How many elements?
	this.count = 0;
	
	// For preparing the form for submission (removing last field if empty)
	this.check = function(){		
		// Check last row's inputs are both empty
		if(this.file.value == '' && this.description.value == '' && this.count > 1){
			this.list_target.removeChild(this.file.parentNode.parentNode);
		}
		
		// Iterate though all values check for emptyness
		var elements = this.list_target.getElementsByTagName('input');
		for(var i in elements){
			if(elements[i].value == '' && (elements[i].type == 'text' || elements[i].type == 'file')){
				alert("Please fill in all the fields.");
				return false;
			}
		}
	};

	// For creating a new file box
	this.create = function(){
		// Element counter
		this.count++;
		
		// Add new row
		var new_row = document.createElement('tr');
		
		// Cells
		var new_desc_cell = document.createElement( 'td' );
		var new_file_cell = document.createElement( 'td' );
		var new_delete_cell = document.createElement( 'td' );

		// Add description field
		this.description = document.createElement('input');
		this.description.name = 'description[]';
		this.description.type = 'text';
		this.description.size = '40';
		this.description.maxlength = '250';
		
		// Add file field
		this.file = document.createElement('input');
		this.file.name = 'file[]';
		this.file.type = 'file';

		//Delete button
		this.deleter = document.createElement('input');
		this.deleter.type = 'button';
		this.deleter.value = 'Delete';
		this.deleter.disabled = 'true';
		
		// Delete function
		this.deleter.onclick = function(){
			// Decrement counter
			this.multi_selector.count--;

			// Re-enable input element (if it's disabled)
			this.multi_selector.file.disabled = false;
			this.multi_selector.description.disabled = false;

			// Remove element from form
			this.multi_selector.list_target.removeChild(this.parentNode.parentNode);
		};
		
		// Provide a reference to this instance
		this.file.multi_selector = this;
		this.description.multi_selector = this;
		this.deleter.multi_selector = this;
			
		// What to do when a file is selected or description entered
		this.file.onchange = this.description.onchange = function(){

			/* Temporary fix for JPC by Steve
			 */
			this.multi_selector.description.value = this.multi_selector.file.value.substring(0, this.multi_selector.file.value.lastIndexOf("."));

			// Make sure both fields are entered
			if(this.multi_selector.file.value != "" && this.multi_selector.description.value != ""){
				// Now unlock the delete button
				this.multi_selector.deleter.disabled = false;
				
				// Update list
				this.multi_selector.create();
				
				// If we've reached maximum number, disable input element
				if(this.multi_selector.max != -1 && this.multi_selector.count > this.multi_selector.max){
					this.multi_selector.file.disabled = true;
					this.multi_selector.description.disabled = true;
				};
			}
		};
				
		// Add inputs to their cells
		new_desc_cell.appendChild(this.description);
		new_file_cell.appendChild(this.file);
		new_delete_cell.appendChild(this.deleter);
		
		// Append cells
		new_row.appendChild(new_desc_cell);
		new_row.appendChild(new_file_cell);
		new_row.appendChild(new_delete_cell);

		// Add it to the list
		this.list_target.appendChild(new_row);
	};
	
	// For initializing
	this.init = function(list_target, max){
		// Where to write the list
		this.list_target = list_target;
		
		// Is there a maximum?
		if( max ){
			this.max = max;
		} else {
			this.max = -1;
		};
		
		// Create initial file
		this.create();
	};
};