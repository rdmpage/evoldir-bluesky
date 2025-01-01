<?php

require_once (dirname(__FILE__) . '/config.inc.php');

//----------------------------------------------------------------------------------------
// http://stackoverflow.com/questions/247678/how-does-mediawiki-compose-the-image-paths
function hash_to_path_array($hash)
{
	preg_match('/^(..)(..)(..)/', $hash, $matches);
	
	$hash_path_parts = array();
	$hash_path_parts[] = $matches[1];
	$hash_path_parts[] = $matches[2];
	$hash_path_parts[] = $matches[3];

	return $hash_path_parts;
}

//----------------------------------------------------------------------------------------
// Return path for a sha1
function hash_to_path($hash)
{
	$hash_path_parts = hash_to_path_array($hash);
	
	$hash_path = join("/", $hash_path_parts);

	return $hash_path;
}

//----------------------------------------------------------------------------------------
// Create nested folders in folder "root" based on sha1
function create_path_from_hash($hash, $root = '')
{	
	$hash_path_parts 	= hash_to_path_array($hash);
	$hash_path 			= hash_to_path($hash);
	
	$filename = $root;
	if ($root != '')
	{
		$filename .= '/';
	}
	$filename .= $hash_path . '/' . $hash;
				
	return $filename;
}

//----------------------------------------------------------------------------------------
// Check if a post with md5 hash already exists
function hash_exists($md5)
{
	global $config;
	
	$found = false;
	
	$post_filepath = create_path_from_hash($md5, $config['posts']);		
	$post_filename = $post_filepath . '.json';
	
	if (file_exists($post_filename))
	{
		$found = true;
	}
	
	return $found;
}

//----------------------------------------------------------------------------------------
function get_post_from_hash($md5)
{
	$post = null;
	
	// ensure hash exists
	if (hash_exists($md5))
	{
		$post_filepath = create_path_from_hash($md5, $config['posts']);		
		$post_filename = $post_filepath . '.json';
		
		$json = file_get_contents($post_filename);
		
		$post = json_decode($json);	
	}
	
	return $post;
}

//----------------------------------------------------------------------------------------
// Check if post already exists, save it if not found
function save_post($post, $force = false)
{
	global $config;
	
	if (hash_exists($post->md5) && !$force)
	{
		return;
	}
	
	$post_filepath = create_path_from_hash($post->md5, $config['posts']);
		
	// store post file
	$post_filename = $post_filepath . '.json';
	
	// ensure hash path exists locally
	$hash_parts = hash_to_path_array($post->md5);
	
	$dir = $config['posts'];
	foreach ($hash_parts as $subdir)
	{
		$dir .= '/' . $subdir;
		
		if (!file_exists($dir))
		{
			$oldumask = umask(0); 
			mkdir($dir, 0777);
			umask($oldumask);
		}
	}
	
	file_put_contents($post_filename, json_encode($post));
}

?>
