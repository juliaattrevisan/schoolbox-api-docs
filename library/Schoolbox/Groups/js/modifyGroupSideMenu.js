/** 
 * Add a group page link to the side menu
 * It'll slide in/out of the menu, and hide/show the whole menu when there's no items in the container
 */

import { insertElementBefore, insertElementAfter } from '@js/util/domUtils';
import { slideDown, slideUp } from '@js/util/animateUtils';

const sideMenuId = 'side-menu-mygroups';


/**
 * Add the group newLink to the side menu
 * 
 * @param { String } 
 * @param { String }
 */
export async function addGroupToSideMenu (title, href) {
    if (document.getElementById(sideMenuId)) { //may not be set to display in the admin settings 
        const groupLinks = document.getElementById(sideMenuId).querySelector('ul');
        if (groupLinks.children.length === 0) { //show the parent element first if it's empty
            document.getElementById(sideMenuId).classList.remove('hide'); 
            await slideDown(document.getElementById(sideMenuId)); //slide the element parent element in first
        }
        const newElement = getListItem(title, href);
        insertSortedAlphabetically(newElement);
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
    
    if (groupLinks.children.length === 0) { //no sorting required if there's no elements in the list yet
        groupLinks.appendChild(newElement);
        return;
    }

    [...groupLinks.children].forEach((element) => { //otherwise run through the list and place accordingly
        const isAboveElement = collator.compare(element.innerText, newElement.innerText) === 1 ? true : false; 
        if (isAboveElement && !groupLinks.contains(newElement)) { 
            insertElementBefore(element, newElement);
            return;
        }
    });

    if (!groupLinks.contains(newElement)) { //must be at the end if it's not already inserted, so put at the bottom
        insertElementAfter(groupLinks.lastChild, newElement);
    }

}

/**
 * Remove the group from the side menu
 * 
 * @param { Number } 
 */
export async function removeGroupFromSideMenu (id) {
    
    if (document.getElementById(sideMenuId)) { //may not be set to display in the admin settings 

        const groupLinks = document.getElementById(sideMenuId).querySelector('ul');

        const elementToRemove = [...groupLinks.children].find((element) => { //find the correct element by the group.id
            return element.querySelector('a').href.includes(`/${id}/`)
        });

        await slideUp(elementToRemove); //slide the element out first

        groupLinks.removeChild(elementToRemove); //then remove it after
        
        if (groupLinks.children.length === 0) { //ensure the list is hidden if there's no items to show
            await slideUp(document.getElementById(sideMenuId)); //slide the element out first
            document.getElementById(sideMenuId).classList.add('hide'); 
        }

    }

}