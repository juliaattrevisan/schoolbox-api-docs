/**
 * Media playlist plugin.
 * Wraps around mediaelementplayer plugin.
 *
 * Options:
 * * autoNext:        Whether player skips to next track once one track finishes.
 *                    Defaults to true.
 * * autoPlayOnClick: Whether player plays automatically when you click a track.
 *                    Defaults to true.
 */
(function($) {

    var MediaPlaylist = function(element, options) {
        var self = this;
        var defaults = {
            autoNext: true,
            autoPlayOnClick: true
        };
        self.settings = $.extend({}, defaults, options);

        self.playerElement = element.find('[data-role="media-playlist-player"]').mediaelementplayer();
        self.playerTitle   = element.find('[data-role="media-playlist-title"]');
        self.playlist      = element.find('[data-role="media-playlist-track"]');

        if (self.playerElement.length) {
            /**
             * This is a playlist: once a track ends, move onto the next one.
             * (if there is one.)
             */
            if (self.settings.autoNext) {
                self.playerElement.data('mediaelementplayer').$media.on('ended.mediaPlaylist', function (event) {
                    // Get list of hrefs in the playlist,
                    // look for current ID in that list,
                    // play the next one if there is one.
                    var playlistLinks = self.playlist.map(function() {
                        return this.href;
                    }).toArray();
                    var current = playlistLinks.indexOf(this.src);
                    if (current >= 0) {
                        var next = current + 1;
                        if (next < playlistLinks.length) {
                            self.loadTrack(playlistLinks[next], true);
                        }
                    }
                });
            }

            self.playlist.on('click.mediaPlaylist', function (event) {
                event.preventDefault();
                self.loadTrack(this.href, self.settings.autoPlayOnClick);
            });
        }
    };

    /**
     * Loads a track with a given ID.
     *
     * @param href
     * @param autoplay Should the player play automatically? Defaults to false
     */
    MediaPlaylist.prototype.loadTrack = function(href, autoplay) {
        var player = this.playerElement.data('mediaelementplayer');
        player.pause();
        player.setSrc(href);

        // Find the requested track in the playlist
        var playlistLink = this.playlist.filter(function () {
            return href == this.href;
        });
        if (playlistLink.length) {
            this.playerTitle.text(playlistLink.text());
        }
        if (autoplay) {
            player.play();
        }
    };

    $.fn.mediaPlaylist = function(options) {
        this.each(function() {
            var element = $(this);
            element.data('mediaPlaylist', new MediaPlaylist(element, options));
        })
        return this;
    }
})(jQuery);
