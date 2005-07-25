function rollupPosts()
{
    // Check we're working with a DOM compliant browser
    if (document.getElementById && document.createElement)
    {
        var objMenu = document.getElementById('post_data');
        var objNested = objMenu.getElementsByTagName('div');

        // Hide each of the nested unordered list
        for (var i=0; i<objNested.length; i++){
            objNested[i].style.display = 'none';
			objNested[i].className = 'childPost';
		}
	}
}

function showPosts()
{
	rollupPosts();
	
	for (var i=0; i<arguments.length; i++) {
		var post = 'post_' + arguments[i];
	
		if(i==0){
			document.getElementById(post).className = 'parentPost';
		}
		document.getElementById(post).style.display = 'block';
	}
}
