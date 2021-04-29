
/*
 * Check for a specific feature in the brower, typically returning true/false
 *
 */

/*
* To use the devices native capture tools
* 
* @returns { Boolean } 
*/ 
export const hasDeviceCapture = (filname, extension) => {
    const inputElement = document.createElement('input')
	const isSupported = inputElement.capture != undefined;
	return isSupported;
}