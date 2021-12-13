/** 
 * Add a group page to the side menu
 *
 */


/**
 * Add the group link to the side menu
 * 
 * @param { String } 
 * @param { String }
 */
export function addGroupToSideMenu (title, href) {
    
    const groupLinks = document.getElementById('side-menu-mygroups').querySelector('ul');
    
    if (groupLinks) { //may not be set to display in the admin settings 

        const link = document.createElement('a');
        link.setAttribute('href', href);
        link.innerText = title;
        
        const linkListItem = document.createElement('li');
        linkListItem.appendChild(link);
        groupLinks.appendChild(linkListItem);

        const groupLinksArray = [...groupLinks.children]; //get the children so we can sort
        groupLinksArray.sort((a,b) => { //sort alphabetically
            return a.innerText > b.innerText ? 1 : -1;
        });
        groupLinksArray.forEach((node) => { //use the newly sorted array to update the children
            groupLinks.appendChild(node);
        });

        document.getElementById('side-menu-mygroups').classList.remove('hide'); //ensure the list is visible, as this link could have been the first

    }

}

/**
 * Remove the group from the side menu
 * 
 * @param { Number } 
 */
 export function removeGroupFromSideMenu (id) {

    const groupLinks = document.getElementById('side-menu-mygroups').querySelector('ul');
    
    if (groupLinks) { //may not be set to display in the admin settings 

        const groupLinksArray = [...groupLinks.children];
        groupLinksArray.forEach((node, index) => { //look through the list, finding the matched id
            if (node.querySelector('a').href.includes(`/${id}/`)) {
                groupLinks.removeChild(node); //and remove once found
                return;
            }
        });

        if (groupLinks.children.length === 0) { //ensure the list is hidden if there's no items to show
            document.getElementById('side-menu-mygroups').classList.add('hide'); 
        }

    }

}