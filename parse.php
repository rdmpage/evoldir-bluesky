<?php

require_once (dirname(__FILE__) . '/bsky.php');
require_once (dirname(__FILE__) . '/openai.php');
require_once (dirname(__FILE__) . '/store.php');

//----------------------------------------------------------------------------------------
function add_tags(&$post)
{
	$post->tags = array();
	
	switch ($post->heading)
	{
		case 'Conferences':
			$post->tags[] = '#conference';
			break;
			
		case 'GradStudentPositions':
			if (preg_match('/phd/i', substr($post->text, 0, 200)))
			{
				$post->tags[] = '#phd';
			}
			else
			{
				$post->tags[] = '#gradstudent';
			}
			break;

		case 'Jobs':
			$post->tags[] = '#job';
			break;

		case 'PostDocs':
			$post->tags[] = '#postdoc';
			break;
			
		case 'WorkshopsCourses':		
			// try and fugure out which tag is more appropriate
			
			if (preg_match('/course/i', substr($post->text, 0, 200)))
			{
				$post->tags[] = '#course';
			}
			elseif (preg_match('/workshop/i', substr($post->text, 0, 200)))
			{
				$post->tags[] = '#workshop';
			}
			else 
			{
				$post->tags[] = '#course';
				$post->tags[] = '#workshop';
			}
			break;
	
		default:
			break;
	}

}

//----------------------------------------------------------------------------------------
function summarise(&$post)
{
	$prompt = "Summarise this message in 200 characters, and include one relevant URL (if present). The total length of the summary (including URL) must be less than 250 characters.";

	$response = conversation ($prompt, $post->text);

	if ($response != '')
	{
		$tag_string = '';
		if (isset($post->tags))
		{
			$tag_string = ' ' . join(' ', $post->tags);
		}		
	
		$post->summary = $response;
		
		// trim if necessary (300 char limit)
		$bluesky_limit = 300;
		$tag_length = mb_strlen ($tag_string);
		$max_length = $bluesky_limit - $tag_length;

		if (mb_strlen($post->summary) > $max_length)
		{
			// make sure we include link
			$pos =  mb_strpos($post->summary, 'http');
			if ($pos === false)
			{
				$post->summary = mb_substr($post->summary, 0, $max_length - 1) . '…';
			}
			else
			{
				$before =  mb_substr($post->summary, 0, $pos);
				$after  =  mb_substr($post->summary, $pos);
				
				$max_length -=  mb_strlen($after);
				$post->summary = mb_substr($before, 0, $max_length - 2) . '… ' . $after;
			}
		}
		
		// append tags
		if (isset($post->tags))
		{
			$post->summary .= $tag_string;
		}
		
	}
}

//----------------------------------------------------------------------------------------
function process_post($post, $session)
{
	$post->links = array_unique($post->links);			
	$post->text = join("\n", $post->body);
	$post->numchars = strlen($post->text);
	
	$post->md5 = md5($post->text);
	
	if (hash_exists($post->md5))
	{
		echo "Have " . $post->md5 . " already!\n";
	}
	else
	{
		echo "New!\n";
		
		// tag
		add_tags($post);
		
		// summarise
		summarise($post);
		
		// save
		save_post($post);
		
		// show (debugging)
		// print_r($post);
		
		// post to BlueSky if we have a summary
		if (isset($post->summary))
		{
			echo "Post " . $post->md5 . " to BlueSky\n";
			
			echo $post->summary . "\n\n";
			
			post_message($session, $post->summary, true);
		}
		else
		{
			echo "Not posted as no summary\n";
		}				
		
	}

}

//----------------------------------------------------------------------------------------



$session = create_session();

// harvest
$urls = array(
'https://evol.mcmaster.ca/~brian/evoldir/last.day',
'https://evol.mcmaster.ca/~brian/evoldir/last.day-1',
'https://evol.mcmaster.ca/~brian/evoldir/last.day-2',
);

foreach ($urls as $url)
{
	$filename = str_replace('https://evol.mcmaster.ca/~brian/evoldir/', '', $url);
	$filename .= '.txt';

	$text = get($url);
	
	if ($text == '')
	{
		echo "Failed to get posts from EvolDir\n";
		exit();
	}
	
	file_put_contents($filename, $text);
	
	$text = file_get_contents($filename);
	
	$lines = explode("\n", $text);
	
	$post = null;
	
	foreach ($lines as $line)
	{
		if (preg_match('/^[\*]{5,}(?<heading>[^\*]+)[\*]+/', $line, $m))
		{
			// print_r($m);
			
			if ($post)
			{
				process_post($post, $session);
			}
			
			$post = new stdclass;
			$post->heading = $m['heading'];
			$post->body = array();
			$post->links = array();
		}
		else
		{
			if ($post)
			{
				$post->body[] = $line;
				
				if (preg_match('/(https?:\/\/(?:www\.)?[-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b(?:[-a-zA-Z0-9()@:%_\+.~#?&\/=]*))/', $line, $m))
				{
					$url = $m[1];
					$url = preg_replace('/\)\.?$/', '', $url);
					$post->links[] = $url;
				}
			}
		}
	}	
	
	if ($post)
	{
		process_post($post, $session);
	}
}

?>
