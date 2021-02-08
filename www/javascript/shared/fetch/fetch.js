
let fetchAsJson = (url) => {
    return fetch(url)
    .then(response => {
        if (response.ok) {
            return response;
        } else {
            throw new Error('Network response was not ok');
        }
    })
    .then(response => {
        return response.json();
    })
    .catch(error => {
        throw new Error('There has been a problem with your fetch operation:', error);
    });
}

export { fetchAsJson }