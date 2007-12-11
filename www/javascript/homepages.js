function toggleConfig(div){		
	if($$('div#' + div + ' div.componentConfig').getStyle('display') == 'none')
		$$('div#' + div + ' div.componentConfig').setStyle('display', 'inherit')
	else
		$$('div#' + div + ' div.componentConfig').setStyle('display', 'none')
} 