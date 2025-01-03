<?php

// Post to Bluesky, see https://docs.bsky.app/blog/create-post

error_reporting(E_ALL);

require_once(dirname(__FILE__) . '/config.inc.php');
require_once(dirname(__FILE__) . '/HtmlDomParser.php');

use Sunra\PhpSimple\HtmlDomParser;

$debug = true;
$debug = false;

//----------------------------------------------------------------------------------------
function get($url, $format = '')
{
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	
	if ($format != '')
	{
		curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept: " . $format));	
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
	
	curl_close($ch);
	
	return $response;
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
	
	$image = get($url);
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
	}
	
	return $blob;
}

//----------------------------------------------------------------------------------------
function get_card($session, $url)
{
	$card = new stdclass;
	$card->uri = $url;

	$html = get($url);		
	
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
					break;

				case 'og:description':
					$card->description = $meta->content;
					break;
					
				case 'og:image':
					$img_url = $meta->content;
					
					// naive attempt to make relative URL global
					if (!preg_match('/:\/\//', $img_url))
					{
						$img_url = $url . $img_url;
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
	if (!isset($card->title) || !isset($card->description))
	{
		$card = null;
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
	
	$json = get($url);
	
	$obj = json_decode($json);
	
	if ($obj && isset($obj->did))
	{
		$did = $obj->did;
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
		if (preg_match_all('/[$|\W](?<link>https?:\/\/(www\.)?[-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b([-a-zA-Z0-9()@:%_\+.~#?&\/=]*[-a-zA-Z0-9@%_\+~#\/=])?)/', $text, $matches, PREG_OFFSET_CAPTURE))
		{
			foreach ($matches['link'] as $match)
			{
				$link = $match[0];
			
				$facet = new stdclass;
				
				$facet->index = new stdclass;
				$facet->index->byteStart = $match[1];
				$facet->index->byteEnd = $match[1] + strlen($link);
				
				$facet->features = array();
				
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
	
	$card = get_card($session, $url);
	
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
