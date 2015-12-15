(function($) {
    "use strict";
    $.retryPromise = function(internalPromise, times) {
        var deferred = $.Deferred();
        internalPromise()
            .done(function() {
                deferred.resolve(arguments);
            })
            .fail(function() {
                if (times <= 0) {
                    deferred.reject(arguments);
                } else {
                    // Try again
                    return $.retryPromise(internalPromise, times-1);
                    //deferred.then(function() {
                    //    return $.retryPromise(internalPromise, times-1);
                    //});
                    //setTimeout(
                    //    function() {
                    //        deferred.then(function() {
                    //            return $.retryPromise(internalPromise, times-1);
                    //        });
                    //    },
                    //    1000
                    //);
                }
            });

        return deferred.promise();
    }
}(jQuery));