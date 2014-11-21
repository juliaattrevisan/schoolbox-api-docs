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
        var defaults = {
            autoNext: true,
            autoPlayOnClick: true
        };
        var settings = $.extend({}, defaults, options);

        var playerElement = element.find('[data-role="media-playlist-player"]').mediaelementplayer();
        var playerTitle   = element.find('[data-role="media-playlist-title"]');
        var playlist      = element.find('[data-role="media-playlist-track"]');
        var player        = playerElement.data('mediaelementplayer');

        if (playerElement.length) {
            var playlistRels = playlist.map(function() {
                return this.rel;
            }).toArray();

            /**
             * Loads a track with a given ID into the player.
             *
             * @var id
             * @var autoplay if true, play automatically
             */
            var loadTrack = function (id, autoplay) {
                player.pause();
                player.setSrc("/cms/sendVideo.php?type=sbfs&id=" + id);
                // Find the link contents
                var playlistLink = playlist.filter(function () {
                    return id == this.rel;
                });
                if (playlistLink.length) {
                    playerTitle.text(playlistLink.text());
                }
                if (autoplay) {
                    player.play();
                }
            };

            /**
             * This is a playlist: once a track ends, move onto the next one.
             * (if there is one.)
             */
            if (settings.autoNext) {
                player.$media.on('ended', function (event) {
                    var match = this.src.match(/id=(\d+)/);
                    if (match) {
                        var current = playlistRels.indexOf(match[1]);
                        if (current >= 0) {
                            var next = current + 1;
                            if (next < playlistRels.length) {
                                loadTrack(playlistRels[next], true);
                            }
                        }
                    }
                });
            }

            /**
             * Click a file name: load that file into the player
             */
            playlist.click(function (event) {
                event.preventDefault();
                loadTrack($(this).attr("rel"), settings.autoPlayOnClick);
            });
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
