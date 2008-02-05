var myrules = {
	'.sitemap-category p' : function(el){
		el.onclick = function() {
			var catID = el.parentNode.getElementsByClassName("sitemap-catid");
			catID = catID[0].innerHTML;
			
			var icon = el.parentNode.getElementsByClassName("sitemap-icon");
			icon = icon[0].firstChild;
			
			var isOpen = $('category_'+catID);
			if (!isOpen) {
				getDir(el.parentNode, catID);
				icon.src = '/images/icons/sitemap-collapse.gif';
			} else {
				var cat = isOpen.parentNode;
				cat.removeChild(isOpen);
				icon.src = '/images/icons/sitemap-expand.gif';
			}
			
			
		}
	}
};	

Behaviour.addLoadEvent(init);
Behaviour.register(myrules);	

function init() {
	getDir($('sitemap'), 0);
	
}

function getDir(el, catID) {
	var url = '/sitemap.php?list=' + catID;
	var myAjax = new Ajax(url, {
						  	method: 'get', 
							onSuccess: function () {
								listDir(el, this.response.text, catID);
							}
							}).request();
}

function listDir(el, data, catID) {
	
	//el.innerHTML = data;
	var div = document.createElement('div');
	div.innerHTML = data;
	div.id = 'category_' + catID;
	div.style.marginLeft = '10px';
	el.appendChild(div);
	Behaviour.apply();

}
