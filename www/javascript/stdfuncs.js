function formatName (userInfo, format) {
    if (!format){
        format = '{title} {firstname} {lastname}';
    }

    for (var tagName in userInfo){
        format = format.replace('{' + tagName + '}', userInfo[tagName]);
    }

    return format;
}