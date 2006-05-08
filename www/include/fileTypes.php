<?php
/************************************************************
*                                                           *
*         Copyright (c) 2003-2004 Lektec Consulting         *
*                                                           *
*	This array defines filetypes allowed in the CMS and the *
*	appropriate images and load style.						*
*   $Id: fileTypes.php,v 1.4 2004/07/21 00:25:59 jleckie Exp $						    
*                                                           *
************************************************************/

//Format:
//array("mime/type" => array("image", "inline/download", array("allowed extensions"))
//1 - inline, 0 - download

$allowedFileTypes = array(
        "image/gif" => array("image-gif.png", 1, array("gif") ),
        "text/html"=> array("text-html.png", 1, array("html", "htm") ),
        "text/plain" => array("textfile.png", 1, array("txt") ),
        "text/richtext" => array("application-rtf.png",1, array("rtf", "rtx") ),
        "text/css" => array("textfile.png", 1, array("css") ),
        "image/jpeg" => array("image-jpeg.png", 1, array("jpg", "jpeg", "jpe") ),
        "image/pjpeg" => array("image-jpeg.png", 1, array("pjpg", "jpg", "jpeg", "jpe") ),
        "image/png" => array("image-png.png",1, array("png") ),
        "image/bmp" => array("image-bmp.png",1, array("bmp") ),
        "application/pdf" => array("application-pdf.png",1, array("pdf") ),
        "application/msword" => array("application-msword.png",1, array("doc") ),
        "application/vnd.ms-powerpoint" => array("application-vnd.ms-powerpoint.png",1, array("ppt", "pps") ),
        "application/vnd.ms-excel" => array("application-vnd.ms-excel.png",1, array("xls", "csv") ),
        "application/x-zip-compressed" => array("compressed.png",0, array("zip") ),
        "application/postscript" => array("application-postscript.png",1, array("ps", "ai", "eps") ),
        "application/x-photoshop" => array("image-psd.png",0, array("psd") ),
		"application/ogg" => array("audio-x-wav.png", 1, array("ogg")),
		"application/x-gsp" => array("application-x-none.png", 0, array("gsp")),
		"application/x-shockwave-flash" => array("application-x-none.png",1,array("swf", "cab") ),
		"application/x-Inspiration" => array("application-x-none.png", 0, array("ins", "isf") ),
        "application/octet-stream" => array("application-x-none.png", 0, array("ins", "isf", "ogg", "re3", "mswmm") ),
        "audio/wav" => array("audio-x-wav.png",0, array("wav") ),
        "audio/mpeg" => array("audio-mpeg.png",0, array("mp3", "mp2") ),
        "audio/x-pn-realaudio" => array("application-vnd.rn-realmedia.png",0, array("ram", "rm") ),
        "audio/x-realaudio" => array("audio-x-pn-realaudio.png",0, array("ra") ),
        "video/mpeg" => array("video-mpeg.png",0, array("mpg", "mpeg") ),
        "video/quicktime" => array("video-quicktime.png",0, array("qt", "mov") ),
		"video/x-ms-wmv" => array("video-x-msvideo.png", 1, array("wmv")),
        "video/x-msvideo" => array("video-x-msvideo.png",0, array("avi") ),
);
?>
