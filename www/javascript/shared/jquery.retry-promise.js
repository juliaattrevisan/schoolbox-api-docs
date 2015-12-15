(function($) {
    "use strict";
    $.retryPromise = function(internalPromise, times) {
        var deferred = $.Deferred();
        internalPromise()
            .done(function() {
                deferred.resolve(arguments);
            })
            .fail(function() {
                if (times <= 1) {
                    deferred.reject(arguments);
                } else {
                    // Try again
                    return $.retryPromise(internalPromise, times-1)
                        .fail(function() {
                            deferred.reject(arguments);
                        });
                }
            });

        return deferred.promise();
    }
}(jQuery));