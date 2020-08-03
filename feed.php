<?php
	if (strpos($_SERVER["HTTP_ACCEPT"], "application/rss+xml") !== FALSE)
		header("Content-Type: application/rss+xml");
	else
		header("Content-Type: application/xml");

	$user = $_GET["user"];

	$notesContent = file_get_contents("https://api.openstreetmap.org/api/0.6/notes/search?display_name=".urlencode($user)."&closed=7");
	$notes = new SimpleXMLElement($notesContent);
	
	use Bhaktaraz\RSSGenerator\Item;
	use Bhaktaraz\RSSGenerator\Feed;
	use Bhaktaraz\RSSGenerator\Channel;

	require 'vendor/autoload.php';

	$feed = new Feed();
	$channel = new Channel();
	$now = new DateTime();
	$channel
		->title("OSM notes of ".$user)
		->description("This channel contains all open and recently closed notes of the user ".$user)
		->url('http://webmapping.cyou')
		->language('en-US')
		->copyright('OpenStreetMap® is open data, licensed under the Open Data Commons Open Database License (ODbL) by the OpenStreetMap Foundation (OSMF).')
		->pubDate($now->getTimeStamp())
		->lastBuildDate($now->getTimeStamp())
		->updateFrequency(1)
		->updatePeriod('hourly')
		->ttl(1)
		->appendTo($feed);
		
	foreach ($notes as $n) {
		$creator = (!is_array($n->comments->comment) ? $n->comments->comment->user : $n->comments->comment[0]->user);
		if ($creator == "") $creator = "<Anonymous>";
		$desc    = (!is_array($n->comments->comment) ? $n->comments->comment->text : $n->comments->comment[0]->text);
		$desc    = str_replace("  ", " ", str_replace("\n", " ", $desc))

		$html = "";
		$last_date = "";
		foreach($n->comments->comment as $c) {
			$last_date = $c->date;
			if (!isset($c->user)) $user_link = "&lt;Anonymous&gt;"; else $user_link = "<a href=\"{$c->user_url}\">{$c->user}</a>";
			if ($c->action == "opened") {
				$html .= "<p>On {$c->date} $user_link opened the note" .
					(trim($c->text) != "" ? ", writing:</p>\n" . $c->html . "\n" : ".");
			}
			if ($c->action == "closed") {
				$html .= "<p>On {$c->date} $user_link closed the note" . 
					(trim($c->text) != "" ? ", writing:</p>\n" . $c->html . "\n" : ".");
			}
			if ($c->action == "reopened") {
				$html .= "<p>On {$c->date} $user_link reopened the note.";
			}
			if ($c->action == "commented") {
				$html .= "<p>On {$c->date} $user_link writes:</p>\n" . $c->html . "\n";
			}
		}

		$item = new Item();
		$item
		->title(($n->status == "closed" ? "[CLOSED] " : "")."Note " . $n->id . " (".$n->date_created.")")
		->creator($creator)
		->description(str_replace("&","&amp;",$desc))
		->content($html)
		->url("https://osm.org/note/{$n->id}")
		->guid(createGUID($n))
		->pubDate(strtotime($last_date))
		->appendTo($channel);
	}
	
	echo $feed->render();
	
	function createGUID($n) { 
		$token  = sprintf("%09x", $n->id);
		$token .= $n->date;
		$hash   = strtoupper(md5($token));

		$guid   = '';

		$guid  .= 
			substr($hash,  0,  8) . 
			'-' .
			substr($hash,  8,  4) .
			'-' .
			substr($hash, 12,  4) .
			'-' .
			substr($hash, 16,  4) .
			'-' .
			substr($hash, 20, 12);
				
		return $guid;
	}
?>
