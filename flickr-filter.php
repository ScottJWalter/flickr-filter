<?php
# Flickr-Filter 
# -------------
# A simple Flickr RSS stream filter to create a stream of only
# portrait- or landscape-oriented images.  This was done primarily
# as a learning tool, and to provide an easy way to feed a digital
# picture frame.
#
# Author:   Scott J. Walter
#           Epic Media
#           http://www.epicmedia.com/
#
#
# NOTES:
# - You need Dan Coulter's phpFlickr class from http://phpflickr.com
#
# - To reduce traffic against the Flickr API, a simple cache mechanism
#   is implemented, saving the filtered RSS feed to a local directory.
#   You'll need to create a writeable directory on your server and
#   set the $cachedir variable properly.
#
###
#
# Insert your own API key and secret here
$Flickr_API_Key		= 'KEY';
$Flickr_API_Secret	= 'SECRET';

# Specify where on the server you want to store the cachefile.  The cachefile
# contains the modified RSS stream so future requests don't hit the Flickr API
# unnecessarily.
$cachedir	= '.cache/';


#
# NO MODIFICATIONS NEEDED BELOW HERE
#
include ('phpflickr-3.0/phpFlickr.php');

# Simple function to get query parameters, or a default if specified
function clean_get($name, $default = null) {
	return isset($_GET[$name]) ? $_GET[$name] : $default;
}

# determines whether 'Portrait' or 'Landscape' mode requested
function is_oriented($width, $height) {
	global $orientation;
	
	switch ($orientation) {
		case 'portrait' :
			return $height > $width;
			
		case 'landscape' :
			return $width > $height;
	}
	
	return false;
}

# Grab the query params:
#
# user_id		- Flickr UserID
# orientation	- 'portrait' or 'landscape'
# count			- number of images to include in the filtered stream
# flush			- 1 to force a cachefile flush
#
$user_id		= clean_get('user_id');
$orientation	= strtolower(clean_get('orientation', 'landscape'));
$count			= clean_get('count', 10);
$flush			= clean_get('flush', false);

# If no userID specified, don't bother going any further
if ( !$user_id ) die ('You must specify a Flickr userID in the query:  user_id=xxx');

# Establish a Flickr object and get user and photo information
$f 		= new phpFlickr($Flickr_API_Key, $Flickr_API_Secret);

$user	= $f->people_getInfo($user_id);

$photos	= $f->photos_search(array(
									'user_id'	=> $user_id
								,	'sort'		=> 'date-posted-desc'
									)
							);

$now = date("D, d M Y H:i:s O");

header("Content-Type: application/rss+xml");

# Only produce output if there are photos in the stream
if ( $photos ) {
	# Grab the first photo in the stream to get the most recently posted image.
	$p = $f->photos_getInfo($photos['photo'][0]['id']);
	
	# The 'posted' date of that image (along with userID, orientation, and count)
	# is used to build the name of the cachefile as:
	#
	# {SCRIPT_NAME}-{USER_ID}-{ORIENTATION}-{COUNT}-{LAST_POST_DATE}
	#
	$cachefile	= $cachedir
				. basename($_SERVER['SCRIPT_NAME']) 
				. '-' . str_replace('@', '', $user_id)
				. '-' . $orientation
				. '-' . $count
				. '-' . $p['dates']['posted']
				;
	
	if ( file_exists($cachefile) ) {
		if ( !$flush ) {
			echo file_get_contents( $cachefile );
			exit;
		} else {
			# if '&flush=1' is passed in the query, dump the current cachefile
			unlink( $cachefile );
		}
	}
	
	ob_start(); // start the output buffer
}

# Build the RSS stream
#
echo '<?xml version="1.0" encoding="utf-8"?>' . "\n";

?><rss version="2.0"
		xmlns:media="http://search.yahoo.com/mrss/"
		xmlns:dc="http://purl.org/dc/elements/1.1/"
		xmlns:flickr="urn:flickr:"
		>
	<channel>
		<title>Flickr Feed Filter for <?php echo $user['realname'] ?></title>
		<link><?php echo $user['photosurl'] ?></link>
		<description></description>
		<pubDate><?php echo $now ?></pubDate>
		<lastBuildDate><?php echo $now ?></lastBuildDate>
		<generator>http://tools.epicmedia.com/</generator>
<?php

if ( $photos ) {
	$z = 0;
	foreach ( $photos['photo'] as $photo ) {
		$link = 'http://farm' . $photo['farm'] . '.static.flickr.com/' . $photo['server'] . '/' . $photo['id'] . '_' . $photo['secret'] . '.jpg';
		$sizes = $f->photos_getSizes($photo['id']);
		$info = $f->photos_getInfo($photo['id']);
		
		$description	=	'&lt;p&gt;&lt;a href=&quot;' . $user['profileurl'] . '&quot;&gt;'
						.	$user['realname'] . '&lt;/a&gt; posted a photo:&lt;/p&gt;&lt;p&gt;&lt;a href=&quot;'
						.	$user['photosurl'] . $photo['id'] . '/&quot; title=&quot;'
						.	$photo['title'] . '&quot;&gt;&lt;img src=&quot;http://farm'
						.	$photo['farm'] . '.static.flickr.com/'
						.	$photo['server'] . '/'
						.	$photo['id'] . '_'
						.	$photo['secret'] . '_m.jpg&quot; width=&quot;240&quot; height=&quot;143&quot; alt=&quot;'
						.	$photo['title'] . '&quot; /&gt;&lt;/a&gt;&lt;/p&gt;'
						;

		$thumb = null;
		$orig = null;
		
		foreach ( $sizes as $size ) {
			if ( $size['label'] == 'Thumbnail' ) {
				$thumb = $size;
			} elseif ( $size['label'] == 'Original' ) {
				$orig = $size;
			}
		}
		
		if ( is_oriented( $thumb['width'], $thumb['height'] ) ) {
			# if the image has the right orientation, write it to the stream
?>		<item>
			<title><?php echo $info['title'] ?></title>
			<link><?php echo $user['photosurl'] . $photo['id'] . '/' ?></link>
			<description><?php echo $description ?></description>
			<pubDate><?php echo date("D, d M Y H:i:s O", $info['dates']['posted']) ?></pubDate>
			<dc:date.Taken><?php echo $info['dates']['taken'] ?></dc:date.Taken>
			<author flickr:profile="<?php echo $user['profileurl'] ?>">nobody@flickr.com (<?php echo $user['username'] ?>)</author>
			<guid isPermaLink="false"><?php echo $user['photosurl'] . $photo['id'] . '/' ?></guid>
			<media:content url="http://farm<?php echo $photo['farm'] ?>.static.flickr.com/<?php echo $photo['server'] ?>/<?php echo $photo['id'] ?>_<?php echo $photo['secret'] ?>_o.jpg"
				type="image/<?php echo $info['originalformat'] ?>"
				height="<?php echo $orig['height'] ?>"
				width="<?php echo $orig['width'] ?>"
				/>
			<media:title><?php echo $info['title'] ?></media:title>
			<media:thumbnail url="http://farm<?php echo $photo['farm'] ?>.static.flickr.com/<?php echo $photo['server'] ?>/<?php echo $photo['id'] ?>_<?php echo $photo['secret'] ?>_s.jpg"
				height="<?php echo $thumb['height'] ?>" width="<?php echo $thumb['width'] ?>" />
			<media:credit role="photographer"><?php echo $user['username'] ?></media:credit>
		</item>
<?php
			$z++;
		}
		
		if ( $z >= $count ) break;
	}
?>	</channel>
</rss>
<?php

	# Write out the buffer to the cachefile
	#
	$fp = fopen($cachefile, 'w');	
	fwrite($fp, ob_get_contents()); 
	fclose($fp);					
	
	# Send the output to the browser
	ob_end_flush();					
}
?>