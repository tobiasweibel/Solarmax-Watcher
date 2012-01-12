<?php
	//Wez Furlong recently wrote the PHP5 version on his blog (titled HTTP POST from PHP, without cURL):
	// Only with >= PHP5
	function doGetRequest($url, $optional_headers = null)
	{
		$params = array('http' => array(
			'method' => 'GET'
		));
		if ($optional_headers!== null) {
			$params['http']['header'] = $optional_headers;
		}
		$ctx = stream_context_create($params);
		$fp = @fopen($url, 'rb', false, $ctx);
		if (!$fp) {
			throw new Exception("Problem with $url, $php_errormsg");
		}
		$response = @stream_get_contents($fp);
		if ($response == false) {
			throw new Exception("Problem reading data from $url, $php_errormsg");
		}
		return $response;
	} 

	$hashFile = 'hashFile';

	if($_GET['insert']=='insert'){
		$dummy = doGetRequest('http://sourceforge.net/api/file/index/project-id/366768/rss');
		// Get rid of the pubDate which try to destroy the hash concept ;)
		$dummy = preg_replace('%<pubDate>[a-zA-z0-9:,\+ ]+</pubDate>%', 'static', $dummy);

		$fd = fopen($hashFile, 'w');
		$lastHash = fwrite($fd, md5($dummy));
		fclose($fd);

	}
	if($_GET['secret']!='WeDoNotWantBotsInHereWhichWouldResultInALargeAmountOfRequests')
		die('');


	// Dirty method to check if the current saved md5 differ
	// from the md5 created with the file overview as input.
	// If the md5s differ than report this to the user
	// and provide a download link to the latest version.

	// Get current hash
	//TODO Error handling ...
	if(!file_exists($hashFile))
		die('');

	$fd = fopen($hashFile, 'r');
	$lastHash = fread($fd, filesize($hashFile));
	fclose($fd);
	// Do the Request
	$dummy = doGetRequest('http://sourceforge.net/api/file/index/project-id/366768/rss');
	// Get rid of the pubDate which try to destroy the hash concept ;)
	$dummy = preg_replace('%<pubDate>[a-zA-z0-9:,\+ ]+</pubDate>%', 'static', $dummy);
	$newHash = md5($dummy);
	//echo $newHash;
	// Print the result
	if($lastHash != $newHash){
		echo '<b>New version <a href="http://sourceforge.net/projects/solarmaxwatcher/files/latest/download?source=files">available</a>&nbsp;&nbsp;&nbsp;&middot;&nbsp;&nbsp;&nbsp;</b>';
	}else{
		if($_GET['bla']==1)echo 'Your version is up to date :)';
	}
?>
