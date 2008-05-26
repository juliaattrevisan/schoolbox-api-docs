function toggleConfig(div){		
	var obj = document.getElementById(div);
	var div = obj.getElementsByClassName('componentConfig');
	
	if (div && div[0]) {
		div[0].className = 'componentConfigShow';
	} else {
		div = obj.getElementsByClassName('componentConfigShow');
		div[0].className = 'componentConfig';
	}
} 