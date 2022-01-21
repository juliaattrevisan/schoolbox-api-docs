/** 
 * Add a group page to the side menu
 *
 */

import { insertElementBefore, insertElementAfter } from '@js/util/domUtils';
import { slideDown } from '@js/util/animateUtils';

const sideMenuId = 'side-menu-mygroups';


/**
 * Add the group newLink to the side menu
 * 
 * @param { String } 
 * @param { String }
 */
export function addGroupToSideMenu (title, href) {
    if (document.getElementById(sideMenuId)) { //may not be set to display in the admin settings 
        const newElement = getListItem(title, href);
        insertSortedAlphabetically(newElement);
        document.getElementById(sideMenuId).classList.remove('hide'); //ensure the list is visible, as this newLink could have been the first
        slideDown(newElement);
    }
}

/**
 * Create the new list link
 * 
 * @param { String } 
 * @param { String }
 */
function getListItem (title, href) {
    const newLink = document.createElement('a');
    newLink.setAttribute('href', href);
    newLink.innerText = title;
    const newElement = document.createElement('li');
    newElement.appendChild(newLink);
    return newElement;
}

/**
 * Inser the new link into the list in the correct alphabetically sorted position
 * 
 * @param { HTMLLIElement } 
 */
function insertSortedAlphabetically (newElement) {

    const groupLinks = document.getElementById(sideMenuId).querySelector('ul');
    const collator = new Intl.Collator('en', { numeric: true, sensitivity: 'base' }); //to sort naturally

    let isAlreadyInserted = false;
    [...groupLinks.children].forEach((element) => {
        const isAboveElement = collator.compare(element.innerText, newElement.innerText) === 1 ? true : false; //can add current element if true
        if (isAboveElement && !isAlreadyInserted) {
            insertElementBefore(element, newElement);
            isAlreadyInserted = true; //don't keep looping through the list
        }
    });
    if (!isAlreadyInserted) { //must be at the end if it's not already inserted
        insertElementAfter(groupLinks.lastChild, newElement);
    }

}

/**
 * Remove the group from the side menu
 * 
 * @param { Number } 
 */
 export function removeGroupFromSideMenu (id) {
    
    if (document.getElementById(sideMenuId)) { //may not be set to display in the admin settings 

        const groupLinks = document.getElementById(sideMenuId).querySelector('ul');

        [...groupLinks.children].forEach((element) => { //look through the list, finding the matched id
            if (element.querySelector('a').href.includes(`/${id}/`)) {
                groupLinks.removeChild(element); //and remove once found
                return;
            }
        });

        if (groupLinks.children.length === 0) { //ensure the list is hidden if there's no items to show
            document.getElementById('side-menu-mygroups').classList.add('hide'); 
        }

    }

}