/** 
 * Adds this homepage page to my links
 *
 */

import axios from 'axios';

export const initPostToMyLinks = () => {
    // Adds page to my links
    document.getElementById('postToMyLinks').addEventListener('click', (event) => {
        event.preventDefault();
        postLink();
        // $(this).ajaxForm({
        //     url: "/cms/myLinksForm.php",
        //     data: { url: {HOMEPAGE_URL_JS}, description: {HEADING_JS}},
        //     type: 'post'
        // }).on('done.ajaxForm', function() {
        //     location.reload();
        // });
    });
}

/**
 * Save the settings to the DB
 * 
 * @param { Element } 
 * @param { HTMLFormElement } 
 */
const postLink = async () => {
    try {
        await axios.post('/cms/myLinksForm.php', getFormData());
    } catch (error) {
        console.warn(error);
    } finally { 
        location.reload();
    }
}

/**
 * To pass through the required FormData to delete the component in the DB
 * 
 * @param { Element } 
 * 
 * @returns { FormData }  
 */
const getFormData = () => {
    const formData = new FormData();
    formData.append('url', document.getElementById('homepageURL').value);
    formData.append('description', document.getElementById('heading').value);
    debugger;
    return formData;
}