<?php

// Post to Bluesky, see https://docs.bsky.app/blog/create-post

error_reporting(E_ALL);

require_once(dirname(__FILE__) . '/config.inc.php');
require_once(dirname(__FILE__) . '/HtmlDomParser.php');

use Sunra\PhpSimple\HtmlDomParser;

$debug = true;
$debug = false;

//----------------------------------------------------------------------------------------
// return HTTP code and content
function get($url, $format = '')
{
	// We need to be a bit clever as web sites can fail to respond
	$result = new stdclass;
	$result->code = 0;
	$result->content = '';

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	
	if ($format != '')
	{
		curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept: " . $format));	
	}
	else
	{
		// Try and convince the web site that we are a web browser... ;)
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			"Accept: */*",
			"Accept-Language: en-gb",
			"User-agent: Mozilla/5.0 (iPad; U; CPU OS 3_2_1 like Mac OS X; en-us) AppleWebKit/531.21.10 (KHTML, like Gecko) Mobile/7B405" 
			)
		);
	}	
	
	$response = curl_exec($ch);
	if($response == FALSE) 
	{
		$errorText = curl_error($ch);
		curl_close($ch);
		//die($errorText);
		echo $errorText;
		return '';
	}
	
	$info = curl_getinfo($ch);
	$http_code = $info['http_code'];
	
	$result->code = $http_code;
	$result->content = $response;
	
	//print_r($result);
	
	curl_close($ch);
	
	return $result;
}

//----------------------------------------------------------------------------------------
function post($url, $data = '', $format = 'application/json', $authorisation = '')
{
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);  
	
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	
	if ($format != '')
	{
		$header = array("Content-type: " . $format);	
	}
	
	if ($authorisation != '')
	{
		$header[] = 'Authorization: Bearer ' . $authorisation;
	}
	curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
	
	$response = curl_exec($ch);
	if($response == FALSE) 
	{
		$errorText = curl_error($ch);
		curl_close($ch);
		die($errorText);
	}
	
	$info = curl_getinfo($ch);
	$http_code = $info['http_code'];
		
	curl_close($ch);
	
	return $response;
}

//----------------------------------------------------------------------------------------
function image_to_blob($session, $url)
{
	$blob = null;
	
	$response = get($url);
	
	$image = $response->content;
	if ($image != '')
	{
		$finfo = new finfo(FILEINFO_MIME_TYPE);
		$mime_type = $finfo->buffer($image);
	
		$json = post(
			'https://bsky.social/xrpc/com.atproto.repo.uploadBlob',
			$image,
			$mime_type,
			$session->accessJwt
		);
		
		$blob = json_decode($json);
		
		// print_r($blob);
				
		// Bluseky has a size limit, e.g.
		// {"error":"BlobTooLarge","message":"This file is too large. It is 1.83MB but the maximum size is 976.56KB."}
		if (isset($blob->blob->size) && $blob->blob->size > 900000)
		{
			echo "Blob size [" . $blob->blob->size . "] is too large\n";
			$blob = null;
		}
	}
	
	return $blob;
}

//----------------------------------------------------------------------------------------
function get_card($session, $url)
{
	$card = new stdclass;
	
	$card->uri = $url;
	
	$response = get($url);
	
	if ($response->code != 200) return null;

	$html = $response->content;	
	
	if ($html == '')
	{
		return null;
	}
	
	// don't overwhelm DOM parser
	$html = substr($html, 0, 32000);
	
	// meta tags, need to convert to linked data for a subset of tags that
	// will add value
	$dom = HtmlDomParser::str_get_html($html);
		
	if ($dom)
	{	
		foreach ($dom->find('meta') as $meta)
		{
			switch ($meta->property)
			{				
				case 'og:title':
					$card->title = $meta->content;
					$card->title = html_entity_decode($card->title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
					break;

				case 'og:description':
					$card->description = $meta->content;
					$card->description = html_entity_decode($card->description, ENT_QUOTES | ENT_HTML5, 'UTF-8');
					break;
					
				case 'og:image':
					$img_url = $meta->content;
					
					// is URL global?
					if (!preg_match('/^https?:\/\//', $img_url))
					{					
						// no, attempt to make relative URL global					
						// e.g. <meta property="og:image" content="/event/128/logo-2622241876.png">					

						// get base URL for website
						$url_parts = parse_url($url);
						
						if (isset($url_parts['scheme']) && isset($url_parts['host']))
						{
							$base_url = $url_parts['scheme'] . '://' . $url_parts['host'];
							
							if (preg_match('/^\//', $img_url))
							{							
								$img_url = $base_url . $img_url;
							} 
							elseif (preg_match('/^[^\/]/', $img_url))
							{							
								$img_url = $base_url . '/' . $img_url;
							}
						}
					}
					
					$blob = image_to_blob($session, $img_url);
					
					if ($blob)
					{
						$card->thumb = $blob->blob;
					}
					break;
					
				default:
					break;
			}
		}
		
		// If website is dumb and lacks og support
		if (!isset($card->title))
		{
			foreach ($dom->find('title') as $title)
			{
				$card->title = $title->plaintext;
			}
		}
	}
	
	// check card is OK
	
	if (!isset($card->title))
	{
		// card must have a title
		$card = null;
	}
	else
	{
		if (!isset($card->description))
		{
			// must also have a description
			$card->description = $card->title;
		}
	}

	return $card;			
}

//----------------------------------------------------------------------------------------
// Create a Bluesky session, we need this in order to post
function create_session()
{
	global $config;
	
	$parameters = array(
		'identifier' => $config['bluesky_handle'],
		'password' => $config['bluesky_app_password']
	);
	
	$json = post(
		'https://bsky.social/xrpc/com.atproto.server.createSession', 
		json_encode($parameters)
		);
	
	$obj = json_decode($json);
	
	return $obj;
}

//----------------------------------------------------------------------------------------
// Given a user handle resolve it to a 'did'. will accept handles with and without '@'
function resolve_handle($handle)
{
	$did = '';

	// trim '@'
	$handle = preg_replace('/^@/', '', $handle);

	$url = 'https://bsky.social/xrpc/com.atproto.identity.resolveHandle?handle=' . urlencode($handle);
	
	$response = get($url);
	
	if ($response->code == 200)
	{
		$obj = json_decode($response->content);
		
		if ($obj && isset($obj->did))
		{
			$did = $obj->did;
		}
	}
		
	return $did;
}

//----------------------------------------------------------------------------------------
// Post a message using the current session. we parse the text to extract URLs and mentions
function post_message($session, $text, $debug = false)
{
	$ok = false;

	if (isset($session->did))
	{
		$record = new stdclass;
		$record->{'$type'} 	= 'app.bsky.feed.post';
		$record->text 		= $text;
		$record->createdAt 	= date(DATE_RFC3339);
		$record->facets     = array();
		
		// extract any facets
		
		// handles
		if (preg_match_all('/([$|\W](?<handle>@([a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?))/', $text, $matches, PREG_OFFSET_CAPTURE))
		{
			foreach ($matches['handle'] as $match)
			{
				$facet = new stdclass;
				
				$facet->index = new stdclass;
				$facet->index->byteStart = $match[1];
				$facet->index->byteEnd = $match[1] + strlen($match[0]);
				
				$facet->features = array();
				
				$feature = new stdclass;
				$feature->{'$type'} = 'app.bsky.richtext.facet#mention';
				
				$handle_did = resolve_handle($match[0]);
				if ($handle_did  != '')
				{
					$feature->did = $handle_did;				
					$facet->features[] = $feature;				
					$record->facets[] = $facet;
				}
			}
		}
		
		// links
		// try to catch links where people just type "www" instead of http :(
		if (preg_match_all('/[$|\W](?<link>(https?:\/\/|www\.)(www\.)?[-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b([-a-zA-Z0-9()@:%_\+.~#?&\/=]*[-a-zA-Z0-9@%_\+~#\/=])?)/', $text, $matches, PREG_OFFSET_CAPTURE))
		{
			foreach ($matches['link'] as $match)
			{
				$link = $match[0];
			
				$facet = new stdclass;
				
				$facet->index = new stdclass;
				$facet->index->byteStart = $match[1];
				$facet->index->byteEnd = $match[1] + strlen($link);
				
				$facet->features = array();
				
				
				// Ensure link is a URI (do this after we have extracted byteStart 
				// and byteEnd otherwise link might no longer have same length as
				// string in the original post.
	
				// Add protocol to link if we have just www without http
				if (!preg_match('/^https?:\/\//', $link))
				{
					$link = 'http://' . $link;
				}
				
				$feature = new stdclass;
				$feature->{'$type'} = 'app.bsky.richtext.facet#link';
				$feature->uri = $link;				
				$facet->features[] = $feature;	
				$record->facets[] = $facet;
				
				if (!isset($record->embed))
				{
					$card = get_card($session, $link);
					if ($card)
					{
						$record->embed = new stdclass;
						$record->embed->{'$type'} = 'app.bsky.embed.external';
						$record->embed->external = $card;
					}
				}
			}
		}
		
		// hashtags
		if (preg_match_all('/(?:^|\s)(?<tag>#[^\d\s]\S*)(?=\s)?/', $text, $matches, PREG_OFFSET_CAPTURE))
		{
			foreach ($matches['tag'] as $match)
			{
				$tag = $match[0];
				$tag = preg_replace('/\p{P}+$/u', '', $tag);
			
				$facet = new stdclass;				
				$facet->index = new stdclass;
				$facet->index->byteStart = $match[1];
				$facet->index->byteEnd = $match[1] + strlen($tag);
				
				$facet->features = array();
				
				$feature = new stdclass;
				$feature->{'$type'} = 'app.bsky.richtext.facet#tag';
				$feature->tag = preg_replace('/^#/u', '', $tag); // only include text after the #			
				$facet->features[] = $feature;	
				$record->facets[] = $facet;
			}
		}
		
		
		if (count($record->facets) == 0)
		{
			unset($record->facets);
		}
		
		$parameters = array(
			'repo' 			=> $session->did,
			'collection' 	=> 'app.bsky.feed.post',
			'record'		=> $record,
			'langs'			=> array('en')
		);
		
		if ($debug)
		{
			print_r($parameters);
		}
			
		$json = post(
			'https://bsky.social/xrpc/com.atproto.repo.createRecord', 
			json_encode($parameters),
			'application/json',
			$session->accessJwt);
			
		if ($debug)
		{
			echo $json;
			echo "\n";
		}
		
		$result = json_decode($json);
		if ($result)
		{
			if (isset($result->error))
			{
				echo "Badness:\n";
				echo $result->error . ": " . $result->message . "\n";
				exit();
			}
			else
			{
				$ok = true;
			}
		}
	}

	return $ok;
}

// test
if (0)
{
	$session = create_session();
	
	$url = 'https://www.wikipedia.org';
	$url = 'https://peerj.com';
	$url = 'https://zookeys.pensoft.net';
	
	$url = 'https://workshops.evolbio.mpg.de/event/128/';
	$url = 'https://www.prstats.org/course/time-series-analysis-and-forecasting-using-r-and-rstudio-tsaf01/';
	
	//$url = 'https://www.physalia-courses.org/courses-workshops/course58/';
	
	$url = 'https://www.uu.se/en/about-uu/join-us/jobs-and-vacancies/job-details?query=787008';
	
	$url = 'www.senckenberg.de';
	$url = 'www.smnk.de';
	$url = 'www.ben-ami.com';
	
	$card = get_card($session, $url);
	
	print_r($card);
	
	print_r($card);
	
}

if (0)
{
	$session = create_session();
	
	$debug = true;
	if ($debug)
	{
		print_r($session);
	}
	
	post_message($session, 
		"Test post via the API #test #api @rdmpage.bsky.social", $debug);
}


if (0)
{
	$session = create_session();
	
	if ($debug)
	{
		print_r($session);
	}
	
	post_message($session, 
		"Test post via the API. https://zookeys.pensoft.net @rdmpage.bsky.social", $debug);
}

?>
