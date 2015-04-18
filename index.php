<?php
/**
 * Stuporglue's Single-File PHP Gallery
 *
 * 
 * Copyright (C) 2014, Michael Moore <stuporglue@gmail.com>
 *
 * Licensed under the MIT license. 
 *
 * Quick Start: 
 * Place this file in the top-level directory where you want photo galleries to appear. 
 *
 * For more details see https://github.com/stuporglue/InstaGallery
 */


$path = __DIR__;      // The base directory for the photos, defaults to the current directory
$thumbnailSize = 300; // size in pixels
$bgcolor = '#d0d5ee'; // background color


///////////////////////////////////////////////////////////////////////////////////////////
/////   Be careful below here...
///////////////////////////////////////////////////////////////////////////////////////////

/**
 * Verify that the requested path is within the $path
 *
 * @param $path The path to check our request within
 * @return String -- The full path to the target directory
 */
function getTargetPath($path){
    // Find and validate the target path
    $targetdir = $path  . (isset($_REQUEST['d']) ? '/' . $_REQUEST['d'] : '');
    while(strpos($targetdir,'..') !== FALSE){
        die("No double dot paths");
        // Get rid of double dots and make sure that our path is a subdirectory of the $path directory
        // Can't use realpath because symlinks might make something a valid path 
        preg_replace('|/.*?/../|','');
    }

    $valid = strpos(realpath($targetdir),realpath($path)) !== FALSE && is_dir($targetdir);

    if(!$valid){
        header("HTTP/1.0 404 Not Found");
        print "Directory not found";
        exit();
    }

    return $targetdir;
}

/**
 * Make a string pretty for printing
 * @param $name The name to pretty print
 * @return A pretty name string
 */
function prettyName($name){
    $origName = $name;
    $name = basename($name);
    $name = preg_split('/([^\w-]|[_])/',$name);
    $name = array_map('ucfirst',$name);
    $name = implode(' ',array_filter($name));
    if($name === ''){
        return $origName;
    }
    return $name;
}

/**
 * Get the Table of Contents for the navigation
 * @param $path The base path
 * @return An html string for the nav
 */
function getNav($path,$relpath){
    $objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path), RecursiveIteratorIterator::SELF_FIRST);
    $dots = Array('.','..');
    $wrapup = Array();
    foreach($objects as $name => $object){
    if($object->isDir() && !in_array($object->getBaseName(),$dots)){
            $name = explode('/',str_replace($path . '/','',$name));
            $wraplink = &$wrapup;
            $part = array_shift($name);
            while(@is_array($wraplink[$part])){
                $wraplink = &$wraplink[$part];
                $part = array_shift($name);
            }
            if($part[0] != '.'){ // skip hidden files/directories
                $wraplink[$part] = Array();
            }
        }
    }

    $rel = array_filter(explode('/',$relpath));
    $pathparts = Array();
    $navparts[] = "<span><a href='?d=' class='home'>Home</a></span>";

    $builtPath = Array();
    foreach($rel as $curpart){
        uksort($wrapup,function($a,$b){ return strnatcmp(prettyName($a),prettyName($b)); });
        $html = "\n<select class='navchange'>\n";
        foreach($wrapup as $pathpart => $childparts){
            $html .= "<option value='" . implode('/',$builtPath) . (count($builtPath) > 0 ? '/' : '') . $pathpart . "'". ($pathpart == $curpart ? ' selected' : '')  .">" . prettyName($pathpart) . "</option>";
        }
        $wrapup = &$wrapup[$curpart];
        $builtPath[] = $curpart;
        $html .= "</select>\n";
        $navparts[] = $html;
    }

    if(count($wrapup) > 0){
        $html = "\n<select class='navchange'>\n";
        $html .= "<option value='" . implode('/',$builtPath) ."'>--</option>\n";
        uksort($wrapup,function($a,$b){ return strnatcmp(prettyName($a),prettyName($b)); });
        foreach($wrapup as $pathpart => $childparts){
            $html .= "<option value='" . implode('/',$builtPath) . (count($builtPath) > 0 ? '/' : '') . $pathpart . "'>" . prettyName($pathpart) . "</option>";
        }
        $html .= "</select>\n";
        $navparts[] = $html;
    }

    return "<form>" . implode(' :: ',$navparts) . "</form>";
}

/**
 * Get an array of all media in the target directory, with titles if available
 * @param $targetdir The directory to get media from
 *
 * Get titles for each photo. Titles are in a file named "titles.csv" in each directory. 
 * The first column is the file name, the second column is the title/caption to use
 *
 * Return an array where the key is the filename and the value is the title. If no
 * title is found the filename is used.
 */
function getMedia($targetdir){
    $media = Array();
    $html = "";
    $globby = "$targetdir/*.*";
    $files = glob($globby);
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    foreach($files as $filename){
        if(preg_match('/(.*)_thumb\.([a-z]{3})/',$filename)){
            continue;
        }

        $mime = finfo_file($finfo,$filename);
        if(strpos($mime,'image') === 0 || strpos($mime,'video')){
            $media[] = $filename;
        }
    }

    $titles = Array();

    if(is_file($targetdir . '/titles.csv')){
        $fh = fopen($targetdir . '/titles.csv','r');
        while(!feof($fh)){
            $line = fgetcsv($fh);
            if(count($line) >= 2){
                $titles[$line[0]] = $line[1];
            }
        }
        fclose($fh);
    }

    foreach($media as $filename){
        $filename = basename($filename);
        if(!isset($titles[$filename])){
            $titles[$filename] = $filename;
        }
    }

    return $titles;
}

/**
 * Get the list of files from the target directory and generate the appropriate html
 *
 * @param $targetdir The target directory to get media from
 * @param $relpath The relative link for images
 */
function getSlides($targetdir,$relpath){
    $media = getMedia($targetdir);
    $baseurl = dirname($_SERVER['PHP_SELF']);
    $html = '';
    foreach($media as $filename => $title){
            $thumbname = $relpath . '/' . preg_replace('/(.*)\.([a-z]{3})/',"$1" . "_thumb." . "$2",$filename);
            if(!is_file($thumbname)){
                $thumbname = "?d=$relpath&amp;t=$filename";
            }
            $html .= "<div id='$filename' class='thumbnailwrapouter'>";
            $html .= "<span class='thumbnailinner'>";
                $html .= "<a href='$baseurl/$relpath/$filename' title='".htmlentities($title)."' class='swipebox thumbnaillink' rel='album' >";
                    $html .= "<img src='$thumbname' class='thumbnail'/>";
                $html .= "</a>";
            $html .= "</span>";
            $html .= "<div class='filename'>". $title ."</div></div>\n";
    }
    if(count($media) === 0){
        return "<div class='error'>No photos found. Try another directory.</div>";
    }
    return $html;

}

/**
 * Print and possibly save a thumbnail image
 */
function printThumbnail($targetdir,$thumbnailSize){
    $orig = $targetdir . '/' . $_GET['t'];
    $thumb = preg_replace('/(.*)\.([a-z]{3})$/',"$1" . "_thumb." . "$2",$orig);

    if(is_file($thumb)){
        readfile($thumb);
        exit();
    }

    if(!is_file($orig)){
        header("HTTP/1.0 404 Not Found");
        exit();
    }

    // This is going to slow down the user experience...
    if (!extension_loaded('gd') || !function_exists('gd_info')) {
        readfile($orig);
        exit();
    }

    try {
        $image_info = getimagesize($orig);
        switch($image_info[2]){
        case IMAGETYPE_JPEG:
            header('Content-Type: image/jpeg');
            $img = imagecreatefromjpeg($orig);
            $outfunc = 'imagejpeg';
            break;
        case IMAGETYPE_GIF:
            header('Content-Type: image/gif');
            $img = imagecreatefromgif($orig);
            $outfunc = 'imagegif';
            break;
        case IMAGETYPE_PNG:
            header('Content-Type: image/png');
            $img = imagecreatefrompng($orig);
            $outfunc = 'imagepng';
            break;
        default:
            readfile($orig);
            exit();
        }   

        $width = $image_info[0];
        $height = $image_info[1];

        if ($width > $height) {
            // The actual minimum dimension to match the CSS
            $resizeFactor = $thumbnailSize / 0.9;
            $newwidth = $resizeFactor;
            $newheight = floor($height / ($width / $resizeFactor));
        } else {
            // The actual minimum dimension to match the CSS
            $resizeFactor = $thumbnailSize / 0.75;
            $newheight = $resizeFactor;
            $newwidth = floor($width / ($height / $resizeFactor) );
        }   

        $tmpimg = imagecreatetruecolor( $newwidth, $newheight );
        imagecopyresampled($tmpimg, $img, 0, 0, 0, 0, $newwidth, $newheight, $width, $height );
        if(function_exists('imagecrop')){
            $tmpimg = imagecrop($tmpimg,Array(
                'x' => $newwidth / 2 - ($thumbnailSize * 0.9) / 2,
                'y' => $newheight / 2 - ($thumbnailSize * 0.75) / 2,
                'width' => $thumbnailSize * 0.9,
                'height' => $thumbnailSize * 0.75 
            ));
        }
        $outfunc($tmpimg, $thumb);
        if(file_exists($thumb)){
            readfile($thumb);
        }else{
            $outfunc($tmpimg);
        }   
    } catch (Exception $e){
        readfile($orig);
    }
}

$targetdir = getTargetPath($path);
$relpath = trim(str_replace($path,'',$targetdir),'/');

/**
 * Include any addons
 */
$moreHtml = Array();
foreach(glob("instaGallery_*.inc") as $plugin){
    include($plugin);
}

/**
 * Print the thumbnail and exit
 */
if(isset($_GET['t'])){
    printThumbnail($targetdir,$thumbnailSize);    
    exit();
}

$nav = getNav($path,$relpath);
$slides = getSlides($targetdir,$relpath);

//////////////////////
// HTML Page
$title = "Choose a Photo Collection";

if($relpath !== './'){
    $title = explode('/',trim($relpath,'/'));
    $title = array_map('prettyName',$title);
    $title = implode(' | ',$title);
}
?>
<!DOCTYPE HTML>
<html><head>
<meta charset='utf-8'>
<title><?=$title?></title>
<link href='//fonts.googleapis.com/css?family=Shadows+Into+Light' rel='stylesheet' type='text/css'>
<link href='//cdn.rawgit.com/brutaldesign/swipebox/master/src/css/swipebox.min.css' rel='stylesheet' type='text/css'>
<link href="//maxcdn.bootstrapcdn.com/font-awesome/4.2.0/css/font-awesome.min.css" rel="stylesheet">
<link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css">
<style type='text/css'>
html,body {
    margin: 0;
    padding: 0;
    background: <?=$bgcolor?> url('data:image/gif;base64,R0lGODlhBgAGAIABAP///wAAACH5BAEKAAEALAAAAAAGAAYAAAIJRB6geMuOYAMFADs') fixed;
    height: 100%;
    color: #444;
    text-align: center;
}

#nav {
    position: absolute;
    background: rgba(255,255,255,0.8);
    padding: 3px 10px;
    width: calc(100% - 20px);
    text-align: left;
}

#slides {
    padding-top: 25px;
    max-width: 100%;
    border: 2px beveled black;
}

.error {
    margin: 50px calc(25% - 25px/2);
    padding: 25px;
    border: 5px groove #ccc;
    background-color: rgba(255,255,255,0.6);
}

.thumbnailwrapouter {
    display: inline-block;
    height: <?=$thumbnailSize?>px;
    width: <?=$thumbnailSize?>px;
    margin: 10px;
    background-color: white;
    border: 1px solid #ccc;
    -webkit-box-shadow: 6px 10px 13px -1px rgba(94,94,94,0.7);
    -moz-box-shadow: 6px 10px 13px -1px rgba(94,94,94,0.7);
    box-shadow: 6px 10px 13px -1px rgba(94,94,94,0.7);
}

.thumbnailinner {
    display: inline-block;
    height: calc(<?=$thumbnailSize?>px * 0.75);
    width: calc(<?=$thumbnailSize?>px * 0.9);
    margin-top: 15px;
    overflow: hidden;
}

.filename {
    margin: 0 10px;
    font-family: 'Shadows Into Light', cursive;
    font-size: calc(<?=$thumbnailSize?>px * 0.1);
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
    display: inline-block;
}

#ctrlbox {
    z-index: 1000;
    position: absolute;
    top: 0;
    right: 50px;
    height: 50px;
    width: 120px;
    text-align: right;
    color: white;
    font: larger bold;
    cursor: pointer;
}

#ctrlbox i {
    padding: 15px;
}
#footer {
    margin: 30px;
}

#footer a {
    text-decoration: none;
    color: #555;
    font-weight: bold;
}

select {
    padding:3px;
    margin: 0;
    -webkit-border-radius:4px;
    -moz-border-radius:4px;
    border-radius:4px;
    border: solid 1px #999;
    background: #fff;
    color:#000;
    outline:none;
    display: inline-block;
    cursor:pointer;
}

.home {
    text-decoration: none;
    color: #000
}
.home:hover {
    text-decoration: underline;
}

</style>
    <script src='//ajax.googleapis.com/ajax/libs/jquery/2.1.3/jquery.min.js'></script>
    <script src='//cdn.rawgit.com/brutaldesign/swipebox/master/src/js/jquery.swipebox.js'></script>
</head>
<body>
    <div id='nav'>
        <?=$nav?> 
    </div>
    <div id='slides'>
        <?=$slides?>
    </div>
    <div id='footer'>
        <a href='https://github.com/stuporglue/InstaGallery'>Gallery by InstaGallery</a>
    </div>
    <?php print implode("\n",$moreHtml); ?>
    <script>
        $('a.swipebox').swipebox({
            hideBarsDelay: -1,
            afterOpen: function(){
                var ui = $.swipebox.extend();
                var close = $('#swipebox-close');
                var fs = $('<i class="fa fa-arrows-alt"></i>');
                fs.on('click',function(){
                    var elem = $('#swipebox-overlay')[0];
                    if (elem.requestFullscreen) {
                        elem.requestFullscreen();
                    } else if (elem.msRequestFullscreen) {
                        elem.msRequestFullscreen();
                    } else if (elem.mozRequestFullScreen) {
                        elem.mozRequestFullScreen();
                    } else if (elem.webkitRequestFullscreen) {
                        elem.webkitRequestFullscreen();
                    }
                });

                var pp = $('<i id="ppbutton" class="fa fa-play"></i>');
                pp.on('click',function(e){
                    var button = $(e.target);
                    if(button.hasClass('fa-play')){
                        button.removeClass('fa-play').addClass('fa-pause');
                        button.attr('data-intid',window.setInterval(function(){ui.getNext()},5000));
                    }else{
                        button.removeClass('fa-pause').addClass('fa-play');
                        window.clearInterval(button.attr('data-intid'));
                        button.attr('data-intid','');
                    }
                });
                var ctrlbox = $("<div id='ctrlbox'>");
                ctrlbox.append(pp);
                ctrlbox.append(fs);
                close.after(ctrlbox);

                // Play/pause button
                // Spacebar/Enter advances
                // big Fullscreen
            },
            afterClose: function(){
                window.clearInterval($('#ppbutton').attr('data-intid'));
            }
        });

        $('.navchange').on('change',function(e){
            if(('d=' + e.target.value) != document.location.search){
                document.location.search = 'd=' + e.target.value;
            }
        });

        $(document).on('keyup',function(e){
            if((e.keyCode == 32 || e.keyCode == 13) && $('#swipebox-overlay').length > 0){
                $.swipebox.extend().getNext();
            }
        });
    </script>
    </body>
</html>
