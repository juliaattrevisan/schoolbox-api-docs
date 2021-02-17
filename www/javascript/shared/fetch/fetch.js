
/**
 * Return the data from a requested url as json
 *
 * @param url:String
 * @return data:Object
 */
export async function fetchAsJson (url) {
    
    try {

        const myRequest = new Request(url, {
            headers: {
                'Content-Type': 'application/json'
            },
            redirect: 'follow'
        });

		let response = await fetch(myRequest);

		if (!response.ok) {
			throw new Error('Network response was not ok');
        }
        
		let data = await response.json();
        
        return data;

    } catch (error) {
        
        throw new Error('There has been a problem with your fetch operation:', error);
        
	}

}