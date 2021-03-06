<?php

ini_set('memory_limit','2G');
$peachy = '/home/cyberpower678/Peachy/Init.php';
$databaseInc = '/home/cyberpower678/database.inc';
$spambotDataLoc = '/home/cyberpower678/bots/cyberbotii/spambotdata/';
echo "----------STARTING UP SCRIPT----------\nStart Timestamp: ".date('r')."\n\n";
require_once( $peachy );
require_once( $databaseInc );

$site2 = Peachy::newWiki( "meta" );
$site = Peachy::newWiki( "cyberbotii" );
$site->set_runpage( "User:Cyberbot II/Run/SPAM" );

//recovery from a crash or a stop
$status = unserialize( file_get_contents( $spambotDataLoc.'sbstatus' ) );
if( isset( $status['status'] ) && $status['status'] != 'idle' ) {
	//Attempt to recover data state or start over on failure.
	file_put_contents( $spambotDataLoc.'lastcrash', serialize( time() ) );
	$a = $status['bladd'];
	$d = $status['bldeleted'];
	$e = $status['blexception'];
	file_put_contents( $spambotDataLoc.'sbstatus', serialize(array( 'status' => 'recover', 'bladd'=>$a, 'bldeleted'=>$d, 'blexception'=>$e, 'scanprogress'=>'x', 'scantype'=>'x' )) );
	if( !file_exists( $spambotDataLoc.'pagebuffer') ) goto normalrun;
	else {
		$pagebuffer = unserialize( file_get_contents( $spambotDataLoc.'pagebuffer' ) );
		if( !$pagebuffer ) {
			//Attempt retrieving file slice for slice.
			$offset = 0;
			$string = "";
			$size = filesize( $spambotDataLoc.'pagebuffer' );
			while( $offset < $size ) {
				$string .= file_get_contents( $spambotDataLoc.'pagebuffer', false, null, $offset, 10000 );
				$offset += 10000;
			}
			unset( $offset, $size );
			if( !($pagebuffer = unserialize( $string ) ) ) {
				unset( $string );
				goto normalrun;
			}
			unset( $string );
		}
	}
	if( !is_array( $pagebuffer ) ) goto normalrun;
	if( !file_exists( $spambotDataLoc.'rundata') ) goto normalrun;
	else $rundata = unserialize( file_get_contents( $spambotDataLoc.'rundata') );
	if( !is_array( $rundata ) ) goto normalrun;
	if( !isset( $rundata['whitelist'] ) ) goto normalrun;
	else $whitelistregex = $rundata['whitelist'];
	if( !isset( $rundata['blacklist'] ) ) goto normalrun;
	else $blacklistregex = $rundata['blacklist'];
	if( !file_exists( $spambotDataLoc.'exceptions.wl' ) ) goto normalrun;
	else $exceptions = unserialize( file_get_contents( $spambotDataLoc.'exceptions.wl' ) );
	if( !$exceptions ) goto normalrun;
	if( !isset( $rundata['blacklistregex'] ) ) goto normalrun;
	else $blacklistregexarray = $rundata['blacklistregex'];
	if( !isset( $rundata['globalblacklistregex'] ) ) goto normalrun;
	else $globalblacklistregexarray = $rundata['globalblacklistregex'];
	if( !isset( $rundata['whitelistregex'] ) ) goto normalrun;
	else $whitelistregexarray = $rundata['whitelistregex'];
	$dblocal = mysqli_connect( 'p:tools-db', $toolserver_username, $toolserver_password, 's51059__cyberbot' );
	$dbwiki = mysqli_connect( 'p:enwiki.labsdb', $toolserver_username, $toolserver_password, 'enwiki_p' );
}
if( isset( $status['status'] ) && $status['status'] == 'scan' && $status['scantype'] == 'local' ) {
	//Attempt to restart scan at crash point
	if( !isset( $rundata['linkcount'] ) ) goto normalrun;
	else $linkcount = $rundata['linkcount'];
	if( !isset( $rundata['offset'] ) ) goto normalrun;
	else $offset = $rundata['offset'];
	if( !isset( $rundata['starttime'] ) ) goto normalrun;
	else $starttime = $rundata['starttime'];
	if( !isset( $rundata['todelete'] ) ) goto normalrun;
	else $todelete = $rundata['todelete'];
	goto localscan;
}
if( isset( $status['status'] ) && $status['status'] == 'scan' && $status['scantype'] == 'replica' ) {
	//Attempt to restart scan at crash point
	if( !isset( $rundata['linkcount'] ) ) goto normalrun;
	else $linkcount = $rundata['linkcount'];
	if( !isset( $rundata['offset'] ) ) goto normalrun;
	else $offset = $rundata['offset'];
	if( !isset( $rundata['starttime'] ) ) goto normalrun;
	else $starttime = $rundata['starttime'];
	goto wikiscan; 
}
if( isset( $status['status'] ) && $status['status'] == 'process' ) goto findrule;
if( isset( $status['status'] ) && $status['status'] == 'tag' ) goto tagging;
if( isset( $status['status'] ) && $status['status'] == 'remove' ) goto removing;

	normalrun:
	echo "----------RUN TIMESTAMP: ".date('r')."----------\n\n";
	echo "Retrieving blacklists...\n\n";
	$d = 0;
	$a = 0;
	$e = 0;
	$status = array( 'status' => 'start', 'bladd'=>$a, 'bldeleted'=>$d, 'blexception'=>$e, 'scanprogress'=>'x', 'scantype'=>'x' );
	$rundata = array();
	updateStatus();
	$globalblacklistregex = $site2->initPage( 'Spam blacklist' )->get_text();
	$globalblacklistregexarray = explode( "\n", $globalblacklistregex );
	$rundata['globalblacklistregex'] = $globalblacklistregexarray;																	 

	$blacklistregex = $site->initPage( 'MediaWiki:Spam-blacklist' )->get_text();
	$blacklistregexarray = explode( "\n", $blacklistregex );
	$rundata['blacklistregex'] = $blacklistregexarray; 
	$blacklistregex = buildSafeRegexes(array_merge($blacklistregexarray, $globalblacklistregexarray));
	$rundata['blacklist'] = $blacklistregex;

	$whitelistregex = $site->initPage( 'MediaWiki:Spam-whitelist' )->get_text();
	$whitelistregexarray = explode( "\n", $whitelistregex );
	$whitelistregex = buildSafeRegexes($whitelistregexarray);
	$rundata['whitelist'] = $whitelistregex;
	$rundata['whitelistregex'] = $whitelistregexarray;
	
	$dblocal = mysqli_connect( 'p:tools-db', $toolserver_username, $toolserver_password, 's51059__cyberbot' );
	$dbwiki = mysqli_connect( 'p:enwiki.labsdb', $toolserver_username, $toolserver_password, 'enwiki_p' );
	$pagebuffer = array();
	$temp = array();
		
	$exceptions = $site->initPage( 'User:Cyberpower678/spam-exception.js' )->get_text();
	file_put_contents( $spambotDataLoc.'exceptionsraw', $exceptions );
	if( $exceptions == null || $exceptions == "" || $exceptions == false ) exit(1);
	if( !is_null($exceptions) ) {
		$exceptions = explode( "\n", $exceptions );
		$exceptions = stripLines( $exceptions );
		$exceptions = str_replace( "<nowiki>", "", $exceptions );
		$exceptions = str_replace( "</nowiki>", "", $exceptions );
		foreach( $exceptions as $id=>$exception ) {
			if( str_replace( 'ns=', '', $exception ) != $exception ) $temp[] = array( 'ns'=>trim( substr( $exception, strlen("ns=") ) ) );
			else {
				$exception = explode( '|', $exception );
				$temp[] = array( 'page'=>trim( substr( $exception[0], strlen("page=") ) ), 'url'=>trim( substr( $exception[1], strlen("url=") ) ) );
			}
		}
		$exceptions = $temp;
		unset($temp);
	}

	if( !isset( $exceptions[0]['page'] ) && !isset( $exceptions[0]['url'] ) && !isset( $exceptions[0]['ns'] ) ) $exceptions = null;
	file_put_contents( $spambotDataLoc.'exceptions.wl', serialize($exceptions) );
	updateData();
	
	$res = mysqli_query( $dblocal, "SELECT COUNT(*) AS count FROM blacklisted_links;" );
	$linkcount = mysqli_fetch_assoc( $res );
	$linkcount = $linkcount['count'];
	mysqli_free_result( $res );
	$rundata['linkcount'] = $linkcount;
	$offset = 0;
	$rundata['offset'] = $offset;
	//compile the pages containing blacklisted URLs
	echo "Scanning {$linkcount} previously blacklisted links in the database...\n\n";
	$status = array( 'status' => 'scan', 'bladd'=>$a, 'bldeleted'=>$d, 'blexception'=>$e, 'scanprogress'=>"0% (0 of {$linkcount})", 'scantype'=>'local' );
	updateStatus();
	$starttime = time();
	$rundata['starttime'] = $starttime;
	$todelete = array();
	$rundata['todelete'] = $todelete;
	updateData();
	localscan:
	while( $offset < $linkcount ) {
		$i = $offset;
		while ( !($res = mysqli_query( $dblocal, "SELECT * FROM blacklisted_links LIMIT $offset,5000;" )) ) {
			echo "ATTEMPTED: SELECT * FROM blacklisted_links LIMIT $offset,5000;\nERROR: ".mysqli_error( $dblocal )."\n";
			echo "Reconnect to local DB...\n";
			mysqli_close( $dblocal );
			$dblocal = mysqli_connect( 'p:tools-db', $toolserver_username, $toolserver_password, 's51059__cyberbot' );   
		}
		while( $link = mysqli_fetch_assoc( $res ) ) {
			if( regexscan( $link['url'] ) ) {
				if( !isset( $pagebuffer[$link['page']]['object'] ) ) $pagebuffer[$link['page']]['object'] = $site->initPage( null, $link['page']);
				if( !isset( $pagebuffer[$link['page']]['title'] ) ) $pagebuffer[$link['page']]['title'] = $pagebuffer[$link['page']]['object']->get_title();														
				$pagelinks = $pagebuffer[$link['page']]['object']->get_extlinks();
				if( !exceptionCheck( $pagebuffer[$link['page']]['title'], $link['url'] ) ) {
					if( in_array_recursive($link['url'], $pagelinks) ) $pagebuffer[$link['page']]['urls'][] = $link['url'];
					else {
						$todelete[] = array( 'id'=>$link['page'], 'url'=>$link['url'] );
						$d++;
					}  
				}																								  
				else $e++;
			} else {
				$todelete[] = array( 'id'=>$link['page'], 'url'=>$link['url'] );					   
				$d++;
			}
			$i++;
			$completed = ($i/$linkcount)*100;
			$completedin = (((time() - $starttime)*100)/$completed)-(time() - $starttime);
			$completedby = time() + $completedin;
			$status = array( 'status' => 'scan', 'bladd'=>$a, 'bldeleted'=>$d, 'blexception'=>$e, 'scanprogress'=>round($completed, 3)."% ($i of {$linkcount})", 'scantype'=>'local', 'scaneta'=>round($completedby, 0) );
			updateStatus();
		}
		mysqli_free_result( $res );
		$offset += 5000;
		$rundata['offset'] = $offset;
		$rundata['todelete'] = $todelete;
		updateData();
	}
	foreach( $todelete as $item ) {
		while ( !mysqli_query( $dblocal, "DELETE FROM blacklisted_links WHERE `url`='".mysqli_escape_string($dblocal, $item['url'])."'AND `page`='".mysqli_escape_string($dblocal, $item['id'])."';") ) {
			echo "ATTEMPTED: DELETE FROM blacklisted_links WHERE `url`='".mysqli_escape_string($dblocal, $item['url'])."' AND `page`='".mysqli_escape_string($dblocal, $item['id'])."';\nERROR: ".mysqli_error( $dblocal )."\n";
			echo "Reconnect to local DB...\n";
			mysqli_close( $dblocal );
			$dblocal = mysqli_connect( 'p:tools-db', $toolserver_username, $toolserver_password, 's51059__cyberbot' );   
		}
	}
	unset( $todelete );
	unset( $rundata['todelete'] );
	unset( $item );

	if( !file_exists($spambotDataLoc.'global.bl') ) file_put_contents($spambotDataLoc.'global.bl', serialize($globalblacklistregexarray));
	else {
		file_put_contents($spambotDataLoc.'global.bl', file_get_contents($spambotDataLoc.'global.bl'));	
		$globalblacklistregexarray2 = array_diff($globalblacklistregexarray, unserialize(file_get_contents( $spambotDataLoc.'global.bl' )));
		file_put_contents($spambotDataLoc.'global.bl', serialize($globalblacklistregexarray));
	}
	if( !file_exists($spambotDataLoc.'local.bl') || !file_exists($spambotDataLoc.'local.wl') ) {
		file_put_contents($spambotDataLoc.'local.wl', serialize($whitelistregexarray));
		file_put_contents($spambotDataLoc.'local.bl', serialize($blacklistregexarray));
	} else {
		file_put_contents($spambotDataLoc.'sblastrun/local.bl', file_get_contents($spambotDataLoc.'local.bl'));
		file_put_contents($spambotDataLoc.'sblastrun/local.wl', file_get_contents($spambotDataLoc.'local.wl'));
		$blacklistregexarray2 = array_merge(array_diff($blacklistregexarray, unserialize(file_get_contents( $spambotDataLoc.'local.bl' ))), array_diff(unserialize(file_get_contents( $spambotDataLoc.'local.wl' )), $whitelistregexarray));
		file_put_contents($spambotDataLoc.'local.wl', serialize($whitelistregexarray));
		file_put_contents($spambotDataLoc.'local.bl', serialize($blacklistregexarray));  
		if( isset( $globalblacklistregexarray2 ) ) $blacklistregexarray3 = array_merge($blacklistregexarray2, $globalblacklistregexarray2);
	}
	$status = array( 'status' => 'scan', 'bladd'=>$a, 'bldeleted'=>$d, 'scanprogress'=>"Calculating...", 'scantype'=>'replica' );
	updateStatus();

	unset( $globalblacklistregexarray2 );
	unset( $blacklistregexarray2 );
	unset( $globalblacklistregex );
	unset( $old );
	unset( $whitelistregexarray );
	if( isset( $blacklistregexarray3 ) ) echo count( $blacklistregexarray3 ) . " new regexes found to scan...\n\n";
	else echo "Scanning all regexes...\n\n";

	if( !empty($blacklistregexarray3) || !isset($blacklistregexarray3) ) {
		if( isset( $blacklistregexarray3 ) ) {
			$blacklistregex = buildSafeRegexes($blacklistregexarray3);
			unset( $blacklistregexarray3 );
		}
		$rundata['blacklist'] = $blacklistregex;

		while ( !($res = mysqli_query( $dbwiki, "SELECT COUNT(*) AS count FROM externallinks;" )) ) {
			echo "ATTEMPTED: SELECT COUNT(*) AS count FROM externallinks;\nERROR: ".mysqli_error( $dbwiki )."\n";
			echo "Reconnect to enwiki DB...\n";
			mysqli_close( $dbwiki );
			$dbwiki = mysqli_connect( 'p:enwiki.labsdb', $toolserver_username, $toolserver_password, 'enwiki_p' );   
		}
		$linkcount = mysqli_fetch_assoc( $res );
		$linkcount = $linkcount['count'];
		mysqli_free_result( $res );
		$rundata['linkcount'] = $linkcount;
		$offset = 0;
		$rundata['offset'] = $offset;
		$completed = ($offset/$linkcount)*100;
		//compile the pages containing blacklisted URLs
		echo "Scanning {$linkcount} externallinks in the database...\n\n";
		$status = array( 'status' => 'scan', 'bladd'=>$a, 'bldeleted'=>$d, 'blexception'=>$e, 'scanprogress'=>round($completed, 3)."% ($offset of {$linkcount})", 'scantype'=>'replica' );
		updateStatus();
		$starttime = time();
		$rundata['starttime'] = $starttime;
		updateData();
		wikiscan:
		while( $offset < $linkcount ) {
			while ( !($res = mysqli_query( $dbwiki, "SELECT * FROM externallinks LIMIT $offset,15000;" )) ) {
				echo "ATTEMPTED: SELECT * FROM externallinks LIMIT $offset,15000;\nERROR: ".mysqli_error( $dbwiki )."\n";
				echo "Reconnect to enwiki DB...\n";
				mysqli_close( $dbwiki );
				$dbwiki = mysqli_connect( 'p:enwiki.labsdb', $toolserver_username, $toolserver_password, 'enwiki_p' );   
			}
			while( $page = mysqli_fetch_assoc( $res ) ) {
				if( regexscan( $page['el_to'] ) ) {
					if( !isset( $pagebuffer[$page['el_from']] ) ) $pagebuffer[$page['el_from']]['title'] = $site->initPage( null, $page['el_from'])->get_title();
					if( !exceptionCheck( $pagebuffer[$page['el_from']]['title'], $page['el_to'] ) ) {
						if( !isset( $pagebuffer[$page['el_from']]['urls'] ) ) $pagebuffer[$page['el_from']]['urls'] = array();
						if( !in_array_recursive($page['el_to'], $pagebuffer[$page['el_from']]['urls']) ) {
						$pagebuffer[$page['el_from']]['urls'][] = $page['el_to'];
						}
					} else $e++;
					while ( !(mysqli_query( $dblocal, "INSERT INTO blacklisted_links (`url`,`page`) VALUES ('".mysqli_escape_string($dblocal, $page['el_to'])."','".mysqli_escape_string($dblocal, $page['el_from'])."');" )) && mysqli_errno( $dblocal ) != 1062 ) {
						echo "Attempted INSERT INTO blacklisted_links (`url`,`page`) VALUES ('".mysqli_escape_string($dblocal, $page['el_to'])."','".mysqli_escape_string($dblocal, $page['el_from'])."'); with error ".mysqli_errno( $dblocal )."\n\n";
						echo "Reconnecting to local DB...\n";
						mysqli_close( $dblocal );
						$dblocal = mysqli_connect( 'p:tools-db', $toolserver_username, $toolserver_password, 's51059__cyberbot' );   
					}
					$a++;
				}
			}
			mysqli_free_result( $res );
			$offset+=15000;
			$rundata['offset'] = $offset;
			$completed = ($offset/$linkcount)*100;
			$completedin = 2*((((time() - $starttime)*100)/$completed)-(time() - $starttime));
			$completedby = time() + $completedin;
			$status = array( 'status' => 'scan', 'bladd'=>$a, 'bldeleted'=>$d, 'blexception'=>$e, 'scanprogress'=>round($completed, 3)."% ($offset of {$linkcount})", 'scantype'=>'replica', 'scaneta'=>round($completedby, 0) );
			updateStatus();
			updateData();
		}
	}
	
	unset( $rundata['offset'] );
	unset( $rundata['linkcount'] );
	unset( $rundata['starttime'] );
	mysqli_close( $dbwiki );
	mysqli_close( $dblocal );
	
	findrule:
	$globalblacklistregexarray = explode( "\n", $site2->initPage( 'Spam blacklist' )->get_text() );																	 
	$blacklistregexarray = explode( "\n", $site->initPage( 'MediaWiki:Spam-blacklist' )->get_text() );
	$whitelistregex = buildSafeRegexes( explode( "\n", $site->initPage( 'MediaWiki:Spam-whitelist' )->get_text() ) );

	$starttime = time();
	$i = 0;
	$count = count( $pagebuffer );
	$status = array( 'status' => 'process', 'bladd'=>$a, 'bldeleted'=>$d, 'blexception'=>$e, 'scanprogress'=>"0% (0 of $count)", 'scantype'=>'x' );
	updateStatus();
	
	//Check to make sure some things are still updated.  Remove outdated entries.
	$i = 0;
	foreach( $pagebuffer as $id=>$page ) {
		$i++;
		if( empty($page['urls']) || !isset($page['urls']) ) {unset( $pagebuffer[$id] ); continue;}
		//check if it has been whitelisted/deblacklisted during the database scan and make sure it isn't catching itself.
		$pagedata = $site->initPage( null, $id )->get_text();
		preg_match( '/\{\{Blacklisted\-links\|(1\=)?(\n)?((.(\n)?)*?)\|bot\=Cyberbot II(\|invisible=(.*?))?\}\}(\n)?/i', $pagedata, $template );
		if( isset( $template[0] ) ) $pagedata = str_replace( $template[0], "", $pagedata );
		foreach( $page['urls'] as $id2=>$url ) if( ($pagebuffer[$id]['rules'][$id2]=findRule( $url )) === false || isWhitelisted( $url ) || strpos( $pagedata, $url ) === false ) unset( $pagebuffer[$id]['urls'][$id2] );
		if( isset( $pagebuffer[$id]['object'] ) ) unset( $pagebuffer[$id]['object'] );
		if( empty($pagebuffer[$id]['urls']) || !isset($pagebuffer[$id]['urls']) ) unset( $pagebuffer[$id] );
		$completed = ($i/$count)*100;
		$completedin = (((time() - $starttime)*100)/$completed)-(time() - $starttime); 
		$completedby = time() + $completedin;
		$status = array( 'status' => 'process', 'bladd'=>$a, 'bldeleted'=>$d, 'blexception'=>$e, 'scanprogress'=>round($completed, 3)."% ($i of $count)", 'scantype'=>'x', 'scaneta'=>round($completedby, 0) ); 
		updateStatus();
	}
	   
	echo "Added $a links to the local database!\n";
	echo "Deleted $d links from the local database!\n\n";
	echo "Ignored $e links on the blacklist!\n\n";
	
	file_put_contents( $spambotDataLoc.'blacklistedlinks', print_r($pagebuffer, true) );
	//generate tags for each page and tag them.
	updateData();
	tagging:
	echo "Tagging pages...\n\n"; 
	$starttime = time();
	$i = 0;
	$count = count( $pagebuffer );
	$status = array( 'status' => 'tag', 'bladd'=>$a, 'bldeleted'=>$d, 'blexception'=>$e, 'scanprogress'=>"x", 'scantype'=>'x', 'editprogress'=>"0% (0 of $count)" );
	updateStatus();
	foreach( $pagebuffer as $pid=>$page ) {
		$i++;
		$pageobject = $site->initPage( null, $pid );
		$talkpageobject = $pageobject->get_talkID();
		if( !is_null( $talkpageobject ) ) $talkpageobject = $site->initPage( null, $talkpageobject );
		$out = "{{Blacklisted-links|1=";
		$out2 = "";
		foreach ( $page['urls'] as $l=>$url ) {
			$out2 .= "\n*$url";
			$out2 .= "\n*:''Triggered by <code>{$page['rules'][$l]['rule']}</code> on the {$page['rules'][$l]['blacklist']} blacklist''";	
		}
		$out .= "$out2|bot=Cyberbot II|invisible=false}}\n";
		$templates = $pageobject->get_templates();
		$oldtext = $buffer = $pageobject->get_text();
		if( $buffer == "" || is_null( $buffer ) ) continue;
		if( in_array_recursive( 'Template:Blacklisted-links', $templates) ) {
			preg_match( '/\{\{Blacklisted\-links\|(1\=)?(\n)?((.(\n)?)*?)\|bot\=Cyberbot II(\|invisible=(.*?))?\}\}(\n)?/i', $buffer, $template );
			$linkstrings = $template[3];
			$template = $template[0];
			if( trim( $out2 ) == trim( "\n".$linkstrings ) ) {
				goto placenotice;
			}
			$out = str_replace( trim( "\n".$linkstrings ), trim( $out2 ), $template );
			$buffer = str_replace( $template, $out, $buffer );
			if( $oldtext != $buffer ) $success = $pageobject->edit( $buffer, "Updating {{[[Template:Blacklisted-links|Blacklisted-links]]}}.", true );	  
			else $success = false;
		} else {
			preg_match( '/^\s*(?:((?:\s*\{\{\s*(?:about|correct title|dablink|distinguish|for|other\s?(?:hurricaneuses|people|persons|places|uses(?:of)?)|redirect(?:-acronym)?|see\s?(?:also|wiktionary)|selfref|the)\d*\s*(\|(?:\{\{[^{}]*\}\}|[^{}])*)?\}\})+(?:\s*\n)?)\s*)?/i', $buffer, $temp );
			$buffer = preg_replace( '/^\s*(?:((?:\s*\{\{\s*(?:about|correct title|dablink|distinguish|for|other\s?(?:hurricaneuses|people|persons|places|uses(?:of)?)|redirect(?:-acronym)?|see\s?(?:also|wiktionary)|selfref|the)\d*\s*(\|(?:\{\{[^{}]*\}\}|[^{}])*)?\}\})+(?:\s*\n)?)\s*)?/i', $temp[0].$out, $buffer );
			if( $oldtext != $buffer ) $success = $pageobject->edit( $buffer, "Tagging page with {{[[Template:Blacklisted-links|Blacklisted-links]]}}.  Blacklisted links found." );
			else $success = false;
		}
		placenotice:
		/*if( !is_null( $talkpageobject ) && $success !== false ) {
			$out2 = "";
			foreach ( $page['urls'] as $l=>$url ) {
				$out2 .= "\n*<nowiki>$url</nowiki>";
				$out2 .= "\n*:''Triggered by <code>{$page['rules'][$l]['rule']}</code> on the {$page['rules'][$l]['blacklist']} blacklist''";	
			}
			$talkout = "Cyberbot II has detected links on [[".$pageobject->get_title( true )."]] which have been added to the blacklist, either globally or locally. Links tend to be blacklisted because they have a history of being spammed or are highly inappropriate for Wikipedia. The addition will be logged at one of these locations: [[MediaWiki_talk:Spam-blacklist/log|local]] or [[m:Spam_blacklist/log|global]]\n";
			$talkout .= "If you believe the specific link should be exempt from the blacklist, you may [[MediaWiki talk:Spam-whitelist|request that it is white-listed]]. Alternatively, you may request that the link is removed from or altered on the blacklist [[MediaWiki talk:Spam-blacklist|locally]] or [[m:Talk:Spam Blacklist|globally]].\n";
			$talkout .= "When requesting whitelisting, be sure to supply the link to be whitelisted and wrap the link in nowiki tags.\n";
			$talkout .= "Please do not remove the tag until the issue is resolved. You may set the invisible parameter to \"true\" whilst requests to white-list are being processed. Should you require any help with this process, please ask at the [[WP:Help desk|help desk]].\n\n";
			$talkout .= "'''Below is a list of links that were found on the main page:'''\n".$out2;
			$talkout .= "\n\nIf you would like me to provide more information on the talk page, contact [[User:Cyberpower678]] and ask him to program me with more info.\n\nFrom your friendly hard working bot.~~~~";
			$talkpageobject->newsection( $talkout, "Blacklisted Links Found on [[".$pageobject->get_title( true )."]]", "Notification of blacklisted links on [[".$pageobject->get_title( true )."]]." );
		}*/
		$completed = ($i/$count)*100;
		$completedin = (((time() - $starttime)*100)/$completed)-(time() - $starttime);
		$completedby = time() + $completedin; 
		$status = array( 'status' => 'tag', 'bladd'=>$a, 'bldeleted'=>$d, 'blexception'=>$e, 'scanprogress'=>"x", 'scantype'=>'x', 'editprogress'=>round($completed, 3)."% ($i of $count)", 'editeta'=>round($completedby, 0) ); 
		updateStatus();   
	} 

	//search for misplaced tags and remove them.
	removing:
	echo "Removing misplaced tags...\n\n";
	$transclusions = $site->embeddedin( "Template:Blacklisted-links", null, -1 );
	$i=0;
	$count = count( $transclusions );
	$status = array( 'status' => 'remove', 'bladd'=>$a, 'bldeleted'=>$d, 'blexception'=>$e, 'scanprogress'=>"x", 'scantype'=>'x', 'editprogress'=>"0% (0 of $count)" );
	updateStatus();
	foreach( $transclusions as $page ) {
		$i++;
		$pageobject = $site->initPage( $page );
		$talkpageobject = $pageobject->get_talkID();
		if( !is_null( $talkpageobject ) ) $talkpageobject = $site->initPage( null, $talkpageobject );
		if( isset( $pagebuffer[$pageobject->get_id()] ) ) continue;
		$oldtext = $buffer = $pageobject->get_text();
		if( $buffer == "" || is_null( $buffer ) ) continue;
		$buffer = preg_replace( array( '/\{\{Spam\-links\|(1\=)?(\n)?((.(\n)?)*?)\|bot\=Cyberbot II(\|invisible=(.*?))?\}\}(\n)?/i', '/\{\{Blacklisted\-links\|(1\=)?(\n)?((.(\n)?)*?)\|bot\=Cyberbot II(\|invisible=(.*?))?\}\}(\n)?/i' ), '', $buffer );
		if( $oldtext != $buffer ) $success = $pageobject->edit( $buffer, "Removing {{[[Template:Blacklisted-links|Blacklisted-links]]}}.  No blacklisted links were found.", true );
		else $success = false;
		if( !is_null( $talkpageobject ) && $success !== false ) {
			if( ($talkpagedata = $talkpageobject->get_text( false, "Blacklisted Links Found on [[".$pageobject->get_title( true )."]]" ) ) === false ) $talkpagedata = $talkpageobject->get_text( false, "Blacklisted Links Found on the Main Page" );
			if( $talkpagedata !== false ) {
				$talkout = $talkpagedata."\n\n{{done|Resolved}} This issue has been resolved, and I have therefore removed the tag.  No further action is necessary.~~~~";
				$talkpagedata = str_replace( $talkpagedata, $talkout, $talkpageobject->get_text() );
				$talkpageobject->edit( $talkpagedata, "/* Blacklisted Links Found on [[".$pageobject->get_title( true )."]] */ Resolved." );
			}
		}
		$completed = ($i/$count)*100;
		$completedin = (((time() - $starttime)*100)/$completed)-(time() - $starttime);
		$completedby = time() + $completedin; 
		$status = array( 'status' => 'remove', 'bladd'=>$a, 'bldeleted'=>$d, 'blexception'=>$e, 'scanprogress'=>"x", 'scantype'=>'x', 'editprogress'=>round($completed, 3)."% ($i of $count)", 'editeta'=>round($completedby, 0) ); 
		updateStatus();
	}
	$status = array( 'status' => 'idle', 'bladd'=>$a, 'bldeleted'=>$d, 'blexception'=>$e, 'scanprogress'=>"x", 'scantype'=>'x' );
	updateStatus();
	unset( $pagebuffer );
	unset( $transclusions );
	unset( $pageobject );
	unset( $dblocal );
	unset( $dbwiki );
	sleep(900);
	goto normalrun;

//This finds the rule that triggered the blacklist  
function findRule( $link ) {
	global $blacklistregexarray, $globalblacklistregexarray;
	$regexStart = '/(?:https?:)?\/\/+[a-z0-9_\-.]*(';
	$regexEnd = ')'.getRegexEnd( 0 );
	$lines = stripLines( $blacklistregexarray );
	foreach( $lines as $id=>$line ) {
		if( preg_match( $regexStart . str_replace( '/', '\/', preg_replace('|\\\*/|u', '/', $line) ) . $regexEnd, $link ) ) return array( 'blacklist'=>'local', 'rule'=>str_replace( '|', '&#124;', $line ) );
	}
	$lines = stripLines( $globalblacklistregexarray );
	foreach( $lines as $id=>$line ) {
		if( preg_match( $regexStart . str_replace( '/', '\/', preg_replace('|\\\*/|u', '/', $line) ) . $regexEnd, $link ) ) return array( 'blacklist'=>'global', 'rule'=>str_replace( '|', '&#124;', $line ) );
	}
	return false;
}
//Check if it's whitelisted since we started this run.
function isWhitelisted( $url ) {
	global $whitelistregex;
	foreach( $whitelistregex as $wregex ) if( preg_match($wregex, $url) ) return true;
	return false;
}

//This scans the links with the regexes on the blacklist.  If it finds a match, it scans the whitelist to see if it should be ignored.
function regexscan( $link ) {
	global $blacklistregex, $whitelistregex;
	foreach( $blacklistregex as $regex ) {
		if( preg_match($regex, $link) ) {
			foreach( $whitelistregex as $wregex ) if( preg_match($wregex, $link) ) return false;
			return true;
		}
	}
	return false;
}
//generate a status file
function updateStatus() {
	global $status, $spambotDataLoc;
	return file_put_contents( $spambotDataLoc.'sbstatus', serialize($status) );	
}
//generate a data file
function updateData() {
	global $rundata, $pagebuffer, $spambotDataLoc;
	return ( file_put_contents( $spambotDataLoc.'rundata', serialize($rundata) ) && file_put_contents( $spambotDataLoc.'pagebuffer', serialize( $pagebuffer ) ) );
		
}
//make sure the page is on the exceptions list
function exceptionCheck( $page, $url ) {
	global $exceptions;
	if( is_null($exceptions) ) return false;
	foreach( $exceptions as $exception ) {
		if( isset( $exception['ns'] ) ) {
			$temp = explode( ':', $page );
			if( isset( $temp[1] ) && $temp[0] == $exception['ns'] ) return true;
		} else {
			if( $exception['page'] == '*' && $exception['url'] == '*' ) continue;
			if( $page == $exception['page'] || $exception['page'] == '*') {
				if( $url == $exception['url'] || $exception['url'] == '*' ) return true;
			}   
		}
	}
	return false;
}
//This is the spam blacklist engine used in MediaWiki adapted for this script.  This ensures consistency with the actual wiki.
function stripLines( $lines ) {
	return array_filter(
		array_map( 'trim',
			preg_replace( '/#.*$/', '',
				$lines ) ) );
}

function buildSafeRegexes( $lines ) {
	$lines = stripLines( $lines );
	$regexes = buildRegexes( $lines );
	if( validateRegexes( $regexes ) ) {
		return $regexes;
	} else {
		// _Something_ broke... rebuild line-by-line; it'll be
		// slower if there's a lot of blacklist lines, but one
		// broken line won't take out hundreds of its brothers.
		return buildRegexes( $lines, 0 );
	}
}

function buildRegexes( $lines, $batchSize=4096 ) {
	# Make regex
	# It's faster using the S modifier even though it will usually only be run once
	//$regex = 'https?://+[a-z0-9_\-.]*(' . implode( '|', $lines ) . ')';
	//return '/' . str_replace( '/', '\/', preg_replace('|\\\*/|', '/', $regex) ) . '/Sim';
	$regexes = array();
	$regexStart = '/(?:https?:)?\/\/+[a-z0-9_\-.]*(';
	$regexEnd = ')'.getRegexEnd( $batchSize );
	$build = false;
	foreach( $lines as $line ) {
		if( substr( $line, -1, 1 ) == "\\" ) {
			// Final \ will break silently on the batched regexes.
			// Skip it here to avoid breaking the next line;
			// warnings from getBadLines() will still trigger on
			// edit to keep new ones from floating in.
			continue;
		}
		// FIXME: not very robust size check, but should work. :)
		if( $build === false ) {
			$build = $line;
		} elseif( strlen( $build ) + strlen( $line ) > $batchSize ) {
			$regexes[] = $regexStart .
				str_replace( '/', '\/', preg_replace('|\\\*/|u', '/', $build) ) .
				$regexEnd;
			$build = $line;
		} else {
			$build .= '|';
			$build .= $line;
		}
	}
	if( $build !== false ) {
		$regexes[] = $regexStart .
			str_replace( '/', '\/', preg_replace('|\\\*/|u', '/', $build) ) .
			$regexEnd;
	}
	return $regexes;
}

function getRegexEnd( $batchSize ) {
	return ($batchSize > 0 ) ? '/Sim' : '/im';
}

function validateRegexes( $regexes ) {
	foreach( $regexes as $regex ) {
		//wfSuppressWarnings();
		$ok = preg_match( $regex, '' );
		//wfRestoreWarnings();

		if( $ok === false ) {
			return false;
		}
	}
	return true;
}
