<?php

date_default_timezone_set('UTC');

// Where we store posts
$config['posts'] = dirname(__FILE__) . '/posts';

// Environment----------------------------------------------------------------------------
// In development this is a PHP file that is in .gitignore, when deployed these parameters
// will be set on the server
if (file_exists(dirname(__FILE__) . '/env.php'))
{
	include 'env.php';
}

// Bluesky
$config['bluesky_handle'] 		= getenv('BLUESKY_HANDLE');
$config['bluesky_app_password'] = getenv('BLUESKY_APP_PASSWORD');

// OpenAI
$config['openai_key'] 			= getenv('OPENAI_APIKEY');
$config['openai_embeddings'] 	= 'https://api.openai.com/v1/embeddings';
$config['openai_completions'] 	= 'https://api.openai.com/v1/chat/completions';

?>
