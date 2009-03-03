function toggleConfig(div){		
	var editPanel = $(div).getChildren();

	for (i in editPanel)
		if (editPanel[i].hasClass("editPanel"))
			break;

	editPanel[i].toggleClass("hide");
} 