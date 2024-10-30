<?php
/*
Plugin Name:  Image Caption Easy
Version: 0.5
Plugin URI: http://imagecaptioneasy.contentspring.com/
Description: Transforms the alt text of an image into a caption which can be controlled through css.
Author: Mark W. B. Ashcroft
Author URI: http://contentspring.com

Copyright 2008  Mark W. B. Ashcroft  (email : mark [at] contentspring.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

To received a copy of the GNU General Public License write to the 
Free Software Foundation, Inc., 
51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

TO DO:
-If have the same image (in single entry) with same caption (alt) title fix bug.
Workaround for user is to use different image captions (change alt in image).
 
LAST MODIFIED: 7 January 2008.
*/

add_filter('the_content', 'imagecaptioneasy');

//Runs Image Caption Easy
function imagecaptioneasy($html) {

	$c = 0; //set count for each image found.
	
	if ( preg_match_all("/<img(.*?)>/is", $html, $img_matches) == true ) { //look through all img tags
		foreach ($img_matches[0] as $img_meta) {
			//Go through images.
			$image_code = $img_matches[0][$c];
			$image_code_escape = preg_quote($image_code, '/');
	
			$caption_text = ice_extractimageattribute($img_meta, "alt");
			if ( $caption_text != false ) {	
				//Dont add if no alt or ends with file name
				$test_alt = substr($caption_text, strlen($caption_text) - 4);
				if ( $caption_text == '' ) { $c++; continue; } //false
				if ( $caption_text == 'src=' ) { $c++; continue; } //false
				if ( $caption_text == 'align=' ) { $c++; continue; } //false
				if ( $test_alt == '.jpg' ) { $c++; continue; } //false
				if ( $test_alt == '.gif' ) { $c++; continue; } //false
				if ( $test_alt == '.png' ) { $c++; continue; } //false
				if ( $test_alt == '.JPG' ) { $c++; continue; } //false
				if ( $test_alt == '.GIF' ) { $c++; continue; } //false
				if ( $test_alt == '.PNG' ) { $c++; continue; } //false
			} else { 
				$c++; continue; //no title, false
			}
			
			//Image alignment.
			$align = ice_extractimageattribute($img_meta, "align");
			if ( $align === false ) {
				$align = "nowrap"; //set so can be no wrap and centerted.
			}
			//only float left/right/none is supported by css, so override to be nowrap.
			$alignOK = 0;
			if ( $align != 'left' ) { $alignOK = 1; }
			if ( $align != 'right' ) { $alignOK = 1; }
			if ( $align != 'nowrap' ) { $alignOK = 1; }
			if ( $alignOK === 0 ) { $align = "nowrap"; }
			
			//Image width, so know what size to make div.
			$width = ice_extractimageattribute($img_meta, "width");
			if ( $width === false ) {
				$src = ice_extractimageattribute($img_meta, "src");
				$width = ice_getimagewidth($src);
			}			
	
			//And alignment of top of entry or not.
			$top_of_page = ""; //default is not aligned at top.
			if ( $c === 0 ) { //first image
				$pos = strpos($html, "<img", 0);
				$test_ifattop = substr($html, 0, $pos);
				$test_ifattop_strip = strip_tags($test_ifattop);
				$test_ifattop_strip = trim($test_ifattop_strip);
				if ($test_ifattop_strip == '') {
					$top_of_page = "top_";
				}
			} //end if $c === 0 (is the first image)
				
			//If image is hyperlink wraped, extract and insert within the div not encapsulation it.
			$find_this_image_regex = "<a([^>]*?)>" . preg_quote($image_code, '/');
			if ( preg_match("/$find_this_image_regex/i", $html, $image_lined_res) ) {
				//remove hyperlink so can be placed within div latter.
				$find_to_replace = preg_quote($image_lined_res[0], '/') . "(.*?)\<\/a\>";
				$html = preg_replace("/$find_to_replace/", "$image_code", $html);
				$image_code = $image_lined_res[0] . "</a>";
			} //end if found image hyperlinked.

			//Now replace image code with new div-ed image code.
			$to_replace_with = "<div class=\"imagecaptioneasy imagecaptioneasy_" . $top_of_page.$align . "\" style=\"width:" . $width . "px;\">" . $image_code . "<br style=\"clear:both\" /><span>" . $caption_text . "</span></div>";
			$html = preg_replace("/$image_code_escape/", "$to_replace_with", $html);
			
		$c++;
		} //end foreach img
	} //end if found any img
	
	return $html; //completo return results...
	
} //end function (imagecaptioneasy)


//Function to extract elements from within the image code, like: alt, align, src etc.
function ice_extractimageattribute($image_text, $attribute_tag) {
	
	//Use this function instead of preg_match (REGEX) to make it run quicker.
	$posL = strpos($image_text, $attribute_tag);
	if ($posL === false) { return false; }
	$posL = $posL + strlen($attribute_tag) + 2;
	$imageattribute = substr($image_text, $posL);
	$posR = strpos($imageattribute, '"');
	$imageattribute = substr($imageattribute, 0, $posR);
	return $imageattribute;
	
} //end function (ice_extractimageattribute)


//If image does not have width in html uses this function.
function ice_getimagewidth($url) {
	
	if ( strpos($url, get_bloginfo('siteurl')) === false ) { //fix if not absolute url
		if ( substr($url, 0, 4) != 'http' ) {
			if ( substr($url, 0, 1) == '/' ) {
				$url = get_bloginfo('siteurl') . $url;
			} else {
				$url = get_bloginfo('siteurl') . '/'. $url;
			}
		}
	}
	
	error_reporting(E_ERROR);
	
	if (function_exists('gd_info')) {

		if (function_exists('curl_init')) {
			// Use cURL
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL,$url); 
			curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT,25); // set to zero for no timeout
			$result = curl_exec($ch);
			curl_close($ch); //close
		} else {
			// Use file_get_contents. Requires allow_url_fopen = On in php.ini, else try HTTP_Request.
			ini_set('default_socket', 25);
			$result = file_get_contents($url);
			if ($result == '') {
				echo "<strong><big>Image-Caption-Easy Plugin Error!</big></strong>\n";
				echo "<p>cURL and allow_url_fopen are both unavailable on your web server. Read image-caption-easy.php file for more information on using HTTP_Request.</p>\n";
				//If cURL and allow_url_fopen are both unavalable on your web server then try HTTP_Request or use a different web host.
				//For more information on using HTTP_Request: http://pear.php.net/package/HTTP_Request/
			}
		}
	
		if ( imagecreatefromstring($result) == true ) {
			$img = imagecreatefromstring($result);
		} else {
			return ""; 	//nada cant find images width OR could set 128 which is default thumbnail size if can find image or its width.
		}
				
		return imagesx($img); //got it...

	} else { //if no GD try GetImageSize.
	
		if (function_exists('getimagesize')) { 
			
			list ($img_width) = GetImageSize($url);
			return $img_width; //got it...
			
		} else { //nada, nada
			return "";	//nada cant find images width OR could set 128 which is default thumbnail size if can find image or its width.
		} //end if getimagesize
		
	} //end if not GD
	
} //end function (ice_getimagewidth)

?>