<?php
/* 
Dreambox Transcoding Manager - v. 0.2

The MIT License (MIT)

Copyright (c) 2015 Göran Törnqvist

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
*/
session_start();
$config = parse_ini_file("config.php", true);
$ctx = stream_context_create(array('http' => array('timeout' => 3)));

function logger($logfile, $logstring) {
  $fh = fopen($logfile, 'a');
  fwrite($fh, $logstring);
  fclose($fh);
}

function dreamboxChangeChannel($version, $ip, $channel) {
  $ctx = stream_context_create(array('http' => array('timeout' => 3)));
  if ($channel != '') {
    if ($version == "1") {
      $result = @file_get_contents("http://$ip/cgi-bin/zapTo?path=" . urlencode($channel), 0, $ctx);
      return true;
    } else {
      $result = @file_get_contents("http://$ip/web/zap?sRef=" . urlencode($channel), 0, $ctx);
      if (preg_match("/\<e2state\>True\<\/e2state\>/", $result)) {
        return true;
      } else {
        return false;
      }
    }
  } else {
    return false;
  }
}

function dreamboxGetChannel($version, $ip) {
  $ctx = stream_context_create(array('http' => array('timeout' => 3)));
  if ($version == "1") {
    $url = "http://$ip/channels/getcurrent";
    $result = @file_get_contents($url, 0, $ctx);
    if ($result != FALSE) {
      return $result;
    } else {
      return "N/A";
    }
  } else {
    $url = "http://$ip/web/getcurrent";
    $result = @file_get_contents($url, 0, $ctx);
    if ($result != FALSE) {
      $doc = new DOMDocument();
      libxml_use_internal_errors(true);
      $doc->loadHTML(mb_convert_encoding($result, 'HTML-ENTITIES', 'UTF-8'));
      libxml_clear_errors();
      $xml = simplexml_import_dom($doc);
      $channel = $xml->{'body'}->{'e2currentserviceinformation'}->{'e2service'}->{'e2servicename'}->asXML();
      if (strlen($channel) > 1) {
        return $channel;
      } else {
        return "N/A";
      }
    } else {
      return "N/A";
    }
  }
}

$title = $config['global']['title'];
$webaddress = $config['global']['webaddress'];
$browser = get_browser(null, true);
$browsername = $browser['browser'];

$applog=$config['global']['datadir'] . "/" . $config['global']['logfilename'];

# The Remote IP address of the user connecting
if ($config['global']['reverse_proxy_mode'] == 'true') {
        $remotehost = $_SERVER['HTTP_X_FORWARDED_FOR'];
} else {
        $remotehost = $_SERVER['REMOTE_ADDR'];
}

# Get/set username session variable
if (!isset($_SESSION['username']) || empty($_SESSION['username'])) {
  if (!isset($_SERVER["REMOTE_USER"]) || empty($_SERVER["REMOTE_USER"])) {
    $_SESSION['username'] = "Unauthenticated User";
  } else {
    $_SESSION['username'] = $_SERVER["REMOTE_USER"];
  }
  $now=date('Y-m-d H:i:s');
  logger($applog, "$now - Logon by " . $_SESSION['username'] . " from $remotehost using $browsername.\n");
}
$username = $_SESSION['username'];

# Actions below
switch ((isset($_GET['action']) ? $_GET['action'] : '')) {
  case "stream":
    if ($_GET['box'] != '') {
      $box = $_GET['box'];
      if (array_key_exists($box, $config)) {
        $ipaddress=$config[$box]['ipaddress'];
        $port=$config[$box]['port'];
        $description=$config[$box]['description'];
        logger($applog, "$now - User " . $_SESSION['username'] . " from $remotehost started streaming from $description.\n");
        header("Content-Type: application/octet-stream");
        $fd = fopen("http://localhost:$port/",'r');
        fpassthru($fd);
        exit;
      }
    }
  case "get-m3u-file":
    if ($_GET['box'] != '') {
      $box = $_GET['box'];
      if (array_key_exists($box, $config)) {
        $ipaddress=$config[$box]['ipaddress'];
        $port=$config[$box]['port'];
        header("Content-Type: audio/mpegurl");
        header("Content-Disposition: attachment; filename=$box.m3u");
        print "http://$webaddress/?action=stream&box=$box\r\n";
        exit;
      }
    }

  case "startserver":
      # Start vlc transcoding process
      $channel = $_POST['channel'];
      $quality = $_POST['quality'];
      $dreambox = $_POST['dreambox'];
      if (!is_array($config[$dreambox])) {
        $_SESSION['alertmessage'] = "Invalid dreambox!";
        header("Location: index.php");
        exit;
      }
      $logfile = $config['global']['datadir'] . "/" . $config[$dreambox]['ipaddress'] . ".log";
      $pidfile = $config['global']['datadir'] . "/" . $config[$dreambox]['ipaddress'] . ".pid";
      $port = $config[$dreambox]['port'];
      $pid = @file_get_contents($pidfile, 0, $ctx);
      if (is_numeric($pid) && intval($pid) > 0) {
        if (file_exists("/proc/$pid")) {
          $ret = exec("kill -9 $pid");
        }
      }
      if (!dreamboxChangeChannel($config[$dreambox]['enigmaversion'], $config[$dreambox]['ipaddress'], $channel)) {
        $_SESSION['alertmessage'] = "Could not change channel on dreambox, verify that it is up!";
        header("Location: index.php");
        exit;
      }
      if ($config[$dreambox]['enigmaversion'] == '1') {
        $dreamboxurl = "http://" . $config[$dreambox]['ipaddress'] . ":31344";
      } else {
        // Assume Enigma2
        $dreamboxurl = "http://" . $config[$dreambox]['ipaddress'] . ":8001/" . urlencode($channel);
      }
      $transcodeoptions = $config['global']['quality'][$quality];
      $cmd = $config['global']['taskset'] . " -c 0 " . $config['global']['vlc'] . " -v -I dummy --http-reconnect $dreamboxurl --sout='#transcode{" . $transcodeoptions . "}:standard{access=http,mux=ts,dst=:$port}' --sout-mux-caching=5000 >$logfile 2>$logfile & printf \"%u\" $!";
      $pid = system($cmd, $retval);
      if (is_numeric($pid) && intval($pid) > 0) {
        sleep(1);
        if (file_exists("/proc/$pid")) {
          $_SESSION['alertmessage'] = "Transcoding process started successfully (pid $pid)";
          $now=date('Y-m-d H:i:s');
          logger($applog, "$now - Transcoding on " . $config[$dreambox]['description'] . " with quality $quality started successfully by " . $_SESSION['username'] . "\n");
          if (file_put_contents ($pidfile , $pid) == FALSE) {
            $_SESSION['alertmessage'] .= ". Error writing pid file $pidfile !";
          }
        } else {
          $_SESSION['alertmessage'] = "Transcoding process started but died immediately. Check logfile $logfile ...";
        }
      } else {
        $_SESSION['alertmessage'] = "Transcoding process could not be started. Check logfile $logfile ...";
      }
      header("Location: index.php");
      exit;
      break;
}

?>
<html>
<head>
<title><?php echo $title?></title>
<style>
body {
  font-family: Verdana, Arial, "Times New Roman";
}
</style>
<script language="javascript">

</script>
</head>
<body>
<?php
echo "<h2>$title</h2>";
echo "<table border=0>";
echo "<tr><td><u>Logfile:</u></td></tr>";
$file = file($applog);
$file = array_reverse($file);
echo "<tr><td><div style='height:200px;width:830px;border:1px solid #ccc;font:16px/26px Georgia, Garamond, Serif;overflow:auto;'>";
$rows=0;
foreach($file as $row){
    echo $row."<br />";
    $rows++;
    if ($rows >= 100) break;
}
echo "</div></td></tr></table>";
echo "<br/><br/>
<table border=1>
  <tr>
";
    $tdcount = 0;
    foreach ($config as $setting => $dreambox) {
      if (preg_match('/dreambox[1-9]/', $setting)) {
        $boxip = $dreambox['ipaddress'];
        $streamport = $dreambox['port'];
        if ($tdcount >= 2) {
          echo "</tr><tr>";
          $tdcount = 0;
        }
        $tdcount++;

        // Check if dreambox streaming port is up
        $socket=socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => 5, 'usec' => 0));
        socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, array('sec' => 5, 'usec' => 0));
        if ($dreambox['enigmaversion'] == "1") {
          $boxport = "31344";
        } else {
          // Asume enigma 2
          $boxport = "8001";
        }
        $ret = @socket_connect($socket, $boxip, $boxport);
        if ($ret) {
          $boxstatus = "<font color=green>Remote port $boxport is UP</font>";
        } else {
          $boxstatus = "<font color=red>Remote port $boxport is DOWN</font>";
        }

	$pidfile = $config['global']['datadir'] . "/" . $boxip . ".pid";
	$pid = @file_get_contents($pidfile, 0, $ctx);

        // Check if local vlc streaming port is up
        $clients="";
        $socket=socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => 3, 'usec' => 0));
        socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, array('sec' => 3, 'usec' => 0));
        $ret = @socket_connect($socket, "127.0.0.1", $streamport);
        $previewdir = $_SERVER["DOCUMENT_ROOT"] . "/preview/";
        if ($ret) {
          $streamstatus = "<font color=green>Local port $streamport is UP</font>";
          // Create a thumbnail image of the stream using vlc
          exec($config['global']['vlc'] . " http://localhost:$streamport -I dummy --rate=1 --video-filter=scene --vout=dummy --start-time=1 --stop-time=1 --scene-format=jpg --scene-ratio=24 --scene-prefix=preview-$streamport- --scene-path=$previewdir --run-time=1 vlc://quit 1>/dev/null 2>&1");
          $preview = true;
          // Check how many connections the stream currently has using netstat
          $cmd = "netstat -anp | grep ESTABLISHED | grep '$pid/vlc' | grep -v '$boxip' | awk '{ print $5 }' | cut -d':' -f1";
          $output = exec($cmd, $clients);
        } else {
          $preview = false;
          $streamstatus = "<font color=red>Local port $streamport is DOWN</font>";
        }

        echo "
    <td valign=top>
      <table border=0 width=407>
        <tr>
          <td colspan=2 align=center><b>" . $dreambox['description'] . "</b></td>
        </tr>
        <tr>
          <td>Current Channel:</td>
          <td>" . dreamboxGetChannel($dreambox['enigmaversion'], $dreambox['ipaddress']) . "</td>
        </tr>
        <tr>
          <td>Dreambox IP address:</td>
          <td>$boxip</td>
        </tr>
        <tr>
          <td>Dreambox Status:</td>
          <td>$boxstatus</td>
        </tr>
        <tr>
          <td>Transcode Stream Status:</td>
          <td>$streamstatus</td>
        </tr>
        <tr>
          <td valign=top>Connected clients:</td>
          <td>";
        if (is_array($clients)) {
          echo "<ul>";
          foreach ($clients as $client) {
            if ($client != '127.0.0.1')
              echo "<li>$client</li>";
          }
          echo "</ul>";
        }
        echo "</td>
        </tr>";
      // Check if operator is set in config for this dreambox
      $showtranscode = false;
      if (array_key_exists("operators", $dreambox) && is_array($dreambox['operators'])) {
       if (in_array($_SESSION['username'], $dreambox['operators'])) {
         $showtranscode = true;
       } else {
         $showtranscode = false;
       }
      } else {
        $showtranscode = true;
      }
      if ($showtranscode) {
        echo "<tr>
          <td colspan=2>
            <form method=\"POST\" action=\"./?action=startserver\">
              <input type=\"hidden\" name=\"dreambox\" value=\"$setting\"/>
              Channel/Quality:
              <select name=\"channel\">
                <option value=\"\"></option>";
      if ($dreambox['enigmaversion'] == "1") {
        $html = @file_get_contents("http://" . $dreambox['ipaddress'] . "/cgi-bin/getServices?ref=0", 0, $ctx);
        if ($html != FALSE) {
          $rows = str_getcsv($html, "\n"); 
          foreach ($rows as &$row) {
            // Hope User - bouquets (TV) works on all dreamboxes ;)
            if (preg_match("/User - bouquets \(TV\)/", $row)) {
              $row = str_getcsv($row, ";");
              $ref = $row[0];
              break;
            }
          }
          $html = @file_get_contents("http://" . $dreambox['ipaddress'] . "/cgi-bin/getServices?ref=$ref", 0, $ctx);
          $rows = str_getcsv($html, "\n");
          foreach ($rows as &$row) {
            if (preg_match("/" . preg_quote($dreambox['bouquet']) . "/", $row)) {
              $row = str_getcsv($row, ";");
              $ref = $row[0];
              break;
            }
          }
          $html = @file_get_contents("http://" . $dreambox['ipaddress'] . "/cgi-bin/getServices?ref=$ref", 0, $ctx);
          $rows = str_getcsv($html, "\n");
          foreach ($rows as &$row) {
              $row = str_getcsv($row, ";");
              $ref = $row[0];
              $channel = $row[1];
              echo "<option value=\"$ref\">$channel</option>";
          }
        }
      } else {
        // Assume Enigma 2
	$xml = @file_get_contents("http://" . $dreambox['ipaddress'] . "/web/getservices", 0, $ctx);
    	if ($xml != FALSE) {
	        $doc = new DOMDocument();
	        $doc->loadHTML(mb_convert_encoding($xml, 'HTML-ENTITIES', 'UTF-8'));
		$xmlobj = simplexml_import_dom($doc);
	        $favouritesRef = $xmlobj->{'body'}->{'e2servicelist'}->{'e2service'}->{'e2servicereference'};
	}
	if (strlen($favouritesRef) > 10) {
		# Get current channel and EPG from dreambox 1 - assumes no password on Enigma interface!
	        $xml = @file_get_contents("http://" . $dreambox['ipaddress'] . "/web/getservices?sRef=" . urlencode($favouritesRef), 0, $ctx);
        	if ($xml != FALSE) {
                	$doc = new DOMDocument();
	                $doc->loadHTML(mb_convert_encoding($xml, 'HTML-ENTITIES', 'UTF-8'));
        	        $xmlobj = simplexml_import_dom($doc);
        	        $channelList = $xmlobj->{'body'}->{'e2servicelist'}->{'e2service'};
			foreach($channelList as $channelElement) {
				if ($channelElement->e2servicename != '<n/a>')
					echo "<option value=\"" . $channelElement->e2servicereference . "\">" . $channelElement->e2servicename . "</option>";
			}
	        }
	}
      }
	echo "</select>
              <select name=\"quality\">
                <option value=\"normal\">Normal</option>
                <option value=\"low\">Low</option>
                <option value=\"high\">High</option>
              </select>
              <input type=\"submit\" value=\"Transcode Channel\"/>";
        echo "</form>
          </td>
        </tr>";
       } else {
         echo "<tr>
          <td colspan=2 align=center height=50>You donÂ´t have permission to operate this dreambox! You may however stream from it using VLC ...</td>
        </tr>";
       }
        echo "
        <tr>
          <td colspan=2 align=center height=50><a href=\"./?action=get-m3u-file&box=$setting\">OPEN STREAM</a></td>
        </tr>";
       if ($preview) {
         echo "
        <tr>
          <td colspan=2 align=center height=50>Preview</td>
        </tr>
        <tr>
          <td colspan=2 align=center><a href=\"preview/preview-" . $dreambox['port'] . "-00001.jpg\"><img src=\"preview/preview-" . $dreambox['port'] . "-00001.jpg\" width=\"300\"/></a><br/><br/></td>
        </tr>";
       }
       echo "
      </table>
    </td>
";
  }
}
echo "
  </tr>
</table>
";

if(isset($_SESSION['alertmessage']) && !empty($_SESSION['alertmessage'])) {
echo "
<script type=\"text/javascript\">
  alert(\"" . $_SESSION['alertmessage']  . "\");
</script>
";
unset($_SESSION['alertmessage']);
}
?>
</body>
</html>
