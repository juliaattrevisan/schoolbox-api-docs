/** 
 * Adds this homepage page to my links
 *
 */

/**
 * Listen for the button press and then save the link to DB and dipslay it on the page
 */
export const initPostToMyLinks = () => {
    document.getElementById('postToMyLinks').addEventListener('click', (event) => {
        event.preventDefault();
        postLink();
    });
}

/**
 * Save the settings to the DB
 */
const postLink = async () => {
    try {
        await fetch('/cms/myLinksForm.php', {
            method: 'POST',
            body: getFormData(),
        })
    } catch (error) {
        console.warn(error);
    } finally { 
        displayLinkOnPage();
    } 
}

/**
 * To pass through the required FormData to save the page to my links
 * 
 * @returns { FormData }  
 */
const getFormData = () => {
    const formData = new FormData();
    formData.append('url', document.getElementById('homepageURL').value);
    formData.append('description', document.getElementById('heading').value);
    return formData;
}

/**
 * Add the link to the side panel
 */
const displayLinkOnPage = () => {
    
    const link = document.createElement('a');
    link.setAttribute('href', document.getElementById('homepageURL').value);
    link.innerText = document.getElementById('heading').value;
    
    const linkListItem = document.createElement('li');
    linkListItem.appendChild(link);

    const myLinksList = document.getElementById('side-menu-mylinks').querySelector('ul');
    myLinksList.appendChild(linkListItem);

}