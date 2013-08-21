<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
?>
<script type="text/javascript" src="/min/index.php?b=javascript&f=jquery-1.7.1.min.js,jquery.autoclear.min.js,scripts.js,jquery.validate.min.js,jquery.cookie.js,jquery.ui.js,jquery.ui.touch-punch.min.js,calendar/jquery.custom.calendar.js,demo/scripts.js,math/jqmath-etc-0.2.0.min.js,fullcalendar.js"></script>
<script type="text/javascript">M.$mathQ = false; </script>
<link href="/javascript/video-js/video-js.css" rel="stylesheet">
<script src="/javascript/video-js/video.js"></script>
<div class="videoWrapper">
	<video id="socialstream_video_117" class="video-js vjs-default-skin" controls="true" preload="auto"
		   poster="/cms/videoThumb.php?hash=dd39f1af531cab5dcfd04ca3d9c8fb2691d05648&type=poster">
		<source src="/cms/sendVideo.php?hash=dd39f1af531cab5dcfd04ca3d9c8fb2691d05648&type=storage" type='video/mp4'>
	</video>
	<script> var video = _V_("socialstream_video_117", {width: '100%', height: '400px'}); </script>
</div>
<script type="text/javascript">
	$j(function() {
		$j('a.video-link').click(function() {
			var $target = "/cms/sendVideo.php?hash=dd39f1af531cab5dcfd04ca3d9c8fb2691d05648";
//			var $poster = "/cms/videoThumb.php?hash=dd39f1af531cab5dcfd04ca3d9c8fb2691d05648&type=poster";
			var $video = _V_("socialstream_video_117", {width: '100%', height: '400px'});

			$video.ready(function(){
				this.pause();
				$j("#socialstream_video_117_html5_api").attr('poster', $poster);
				$j(".vjs-poster").css({ "background-image": "url("+$poster+")"});
				this.src({ type: "video/mp4", src: $target});
				this.bigPlayButton.show();
			});
			return false;
		});
	});
</script>