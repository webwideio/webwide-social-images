<?php
require('functions.php');
$config = include('config.php');

// Get thread ID
$id = intval($_GET['id']);

if ($id === 0) {
    throw_error('Invalid thread id.');
}

// Do we already have that in the cache? If so, just show us that!
if (file_exists('cache/thread-' . $id . '.png')) {
    header('Content-type: image/png');
    readfile('cache/thread-' . $id . '.png');
    exit;
}

// Create a stream
$opts = [
    'http' => [
        'method' => 'GET',
        'header' => 'XF-Api-Key: ' . $config['api_key'] . PHP_EOL,
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
    ],
  ]
];

// Connect to API & get thread JSON
$context = stream_context_create($opts);
$file = file_get_contents('https://webwide.io/api/threads/' . $id . '/', false, $context);

// Check thread exists
if ($file === false) {
    throw_error('Thread not found.');
}

$threadData = json_decode($file);

// Thread data
$title = truncate(clean($threadData->thread->title), 80);
$username = $threadData->thread->username;
$forum = $threadData->thread->Forum->title;
$subtitle = truncate(clean($username . ' posted a thread in ' . $forum . '...'), 55);
$avatar = $threadData->thread->User->{'avatar_urls'}->o;

// If user has no avatar, show a default one instead
if (empty($avatar) || strlen($avatar) <= 8) { // Check if string is empty or smaller than strlen('https://')
    $avatar = 'assets/default-avatar.jpg';
}

// Card settings
$card_width = 800; // pixels
$card_height = 418 + 0; // pixels
$font = 34.5;
$line_height = 52;
$padding = 32;
$angle = 0;

// Create blank image to print onto
$image = imagecreatefrompng('assets/twitter-template.png');

$light = imagecolorallocate($image, 112, 124, 123);
$dark = imagecolorallocate($image, 37, 44, 44);

// Add subtitle
imagettftext($image, 14.5, $angle, $padding, 47, $light, 'assets/OpenSans-ExtraBold.ttf', $subtitle);

// Break title in to lines
$title_options = text_to_lines($title, $card_width);

// Add title line by line
$i = $padding + 75;
foreach ($title_options['lines'] as $line){
    imagettftext($image, $font, $angle, $padding, $i, $dark, 'assets/OpenSans-ExtraBold.ttf', trim($line));
    $i += $line_height;
}

$i += $padding;

// Create round image from profile picture
if(exif_imagetype($avatar) == IMAGETYPE_PNG) {
	$src = imagecreatefrompng($avatar);	
} else {
	$src = imagecreatefromjpeg($avatar);	
}

$src = imagescale($src, 100, 100);
$src_width = imagesx($src);
$src_height = imagesy($src);
$dstX = $card_width - $src_width - $padding;
$dstY = 33;
$srcX = 0;
$srcY = 0;
$pct = 100;

// Create image mask
$mask = imagecreatefrompng('assets/circle-mask-100.png');
imagecopymerge_alpha($src, $mask, 0, 0, 0, 0, $src_width, 100, 100);
imagedestroy($mask);

// Merge the two images to create the result.
imagecopymerge_alpha($image, $src, $dstX, $dstY, $srcX, $srcY, $src_width, $src_height, $pct);

// Cache it
imagepng($image, 'cache/thread-' . $id . '.png');

// Print image to browser
header('Content-type: image/png');
imagepng($image);
imagedestroy($image);

exit;
