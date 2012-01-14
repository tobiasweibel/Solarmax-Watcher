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

	function getUpdateHash(){
		$dummy = doGetRequest('http://sourceforge.net/api/file/index/project-id/366768/rss');
		// Get rid of the pubDate which try to destroy the hash concept ;)
		return md5(preg_replace('%<pubDate>[a-zA-z0-9:,\+ ]+</pubDate>%', 'static', $dummy));
	}

	$hashFile = 'hashFile';
	$updateAvailFile='update';
	$lastCheckedForUpdateFile='lastUpdateCheck';	// pls forgive me ;)

	// Create a new hash and lastCheckedForUpdate file
	if($_GET['insert']=='insert'){
		file_put_contents($hashFile, getUpdateHash());
		file_put_contents($lastCheckedForUpdateFile, date('dmY'));		
		// who knows ...
		unlink($updateAvailFile);
	}

	if(!file_exists(hashFile))
		die('');
	
	if(file_exists($updateAvailFile)){
		echo '<b>New version <a href="http://sourceforge.net/projects/solarmaxwatcher/files/latest/download?source=files">available</a></b>&nbsp;&nbsp;&nbsp;&middot;&nbsp;&nbsp;&nbsp;';
	}else if(file_get_contents($lastCheckedForUpdateFile) == date('dmY')){
		if($_GET['bla']==1)echo 'Your version is up to date :)';
	}
	// if there was no check today, do it now.
	else{
		// there is a new udpate
		if(file_get_contents($hashFile) != getUpdateHash()){
			file_put_contents($updateAvailFile, '');
			echo '<b>New version <a href="http://sourceforge.net/projects/solarmaxwatcher/files/latest/download?source=files">available</a></b>&nbsp;&nbsp;&nbsp;&middot;&nbsp;&nbsp;&nbsp;';
		}else{
			if($_GET['bla']==1)echo 'Your version is up to date :)';
		}
		file_put_contents($lastCheckedForUpdateFile, date('dmY'));
	}
?>
