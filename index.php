<?php

$GRAPHITE_ROOT = "http://ec2-184-73-92-62.compute-1.amazonaws.com";

function makeGraphiteUrl($targets, $from="-2hours") {
    global $GRAPHITE_ROOT;
    $query = "from=$from&until=now&format=json";
    foreach ($targets as $t) {
        $query .= "&target=$t";
    }
    return $GRAPHITE_ROOT."/render?".$query;
}

function plotMemory($path) {
    $targets = array($path."memory-buffered",
        $path."memory-cached",
        $path."memory-free",
        $path."memory-used");
    $name = "Memory";
    $url = makeGraphiteUrl($targets);
    return array("name"=>$name, "target"=>$url);
}

function plotLoad($path) {
    $targets = array($path."load.shortterm", $path."load.midterm", $path."load.longterm");
    $name = "Load";
    $url = makeGraphiteUrl($targets);
    return array("name"=>$name, "target"=>$url);
}

function plotCpu($path) {
    $targets = array($path."cpu-user", $path."cpu-system", $path."cpu-wait");
    $matches = array();
    preg_match("/cpu-(\d+)/", $path, $matches);
    $cpunum = $matches[1] + 1;
    $name = "CPU $cpunum";
    $url = makeGraphiteUrl($targets);
    return array("name"=>$name, "target"=>$url);
}

function plotDf($path) {
    $targets = array($path."df_complex-used", $path."df_complex-free");
    $matches = array();
    preg_match("/df-([^.]+)/", $path, $matches);
    $diskname = $matches[1];
    if ($diskname == "root") {
        $diskname = "/";
    }
    $name = "Disk free ($diskname)";
    $url = makeGraphiteUrl($targets);
    return array("name"=>$name, "target"=>$url);
}

function plotDisk($path) {
    $targets = array($path."disk_ops.read", $path."disk_ops.write");
    $matches = array();
    preg_match("/disk-([^.]+)/", $path, $matches);
    $diskname = $matches[1];
    $name = "Disk ($diskname)";
    $url = makeGraphiteUrl($targets);
    return array("name"=>$name, "target"=>$url);
}

function plotInterface($path) {
    $targets = array($path."if_packets.rx", $path."if_packets.tx");
    $matches = array();
    preg_match("/interface-([^.]+)/", $path, $matches);
    $interface = $matches[1];
    $name = "Interface $interface";
    $url = makeGraphiteUrl($targets);
    return array("name"=>$name, "target"=>$url);
}

function getJsonResponse($url, $postdata) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    $response = curl_exec( $ch );
    return json_decode($response);
}

function doGraphiteFind($query) {
    global $GRAPHITE_ROOT;
    $url = $GRAPHITE_ROOT . "/metrics/find/";
    $data = array("query" => $query,
                  "format" => "completer");
    return getJsonResponse($url, $data);
}

function getUsers() {
    $users = doGraphiteFind("");
    $ret = array();
    foreach ($users->metrics as $u) {
        array_push($ret, $u->name);
    }
    return $ret;
}

function getServers($user) {
    $servers = doGraphiteFind("$user.");
    $ret = array();
    foreach ($servers->metrics as $u) {
        array_push($ret, $u->name);
    }
    return $ret;
}

function getPlugins($user, $server) {
    $plugins = doGraphiteFind("$user.$server.");
    return $plugins;
}

function startswith($str, $startswith) {
    return strpos($str, $startswith) === 0;
}

$clientuser = $_GET["user"];
$clienthost = $_GET["host"];

if ($clientuser && $clienthost) {
    // Show all services for this machine on this client
    $plugins = getPlugins($clientuser, $clienthost);
    $plugin_config = array();
    foreach ($plugins->metrics as $p) {
        if ($p->name == "load") {
            //$plugin_config[] = plotLoad($p->path);
        } else if ($p->name == "memory") {
            $plugin_config[] = plotMemory($p->path);
        } else if (startswith($p->name, "cpu-")) {
            $plugin_config[] = plotCpu($p->path);
        } else if (startswith($p->name, "df-")) {
            $plugin_config[] = plotDf($p->path);
        } else if (startswith($p->name, "disk-")) {
            $plugin_config[] = plotDisk($p->path);
        } else if (startswith($p->name, "interface-")) {
            $plugin_config[] = plotInterface($p->path);
        }
    }

} else if ($clientuser && !$clienthost) {
    // We've selected a user, show all hosts
    $available_hosts = getServers($clientuser);
    echo "<h2>Customer: $clientuser</h2>\n";
    echo "<h2>Available servers</h2>\n";
    foreach ($available_hosts as $host) {
        echo "<li><a href=\"?user=$clientuser&host=$host\">$host</a></li>\n";
    }

} else {
    // No user selected, show all users
    $available_users = getUsers();
    echo "<h2>Available customers</h2>\n";
    foreach ($available_users as $user) {
        echo "<li><a href=\"?user=$user\">$user</a></li>\n";
    }
}

$containers = array("#g1-1", "#g1-2", "#g1-3", "#g2-1",
    "#g2-2", "#g2-3", "#g3-1", "#g3-2", "#g3-3");

$config = json_encode($plugin_config);
$containers = json_encode($containers);

?>

<!DOCTYPE html>
<html>
  <head>
    <title>Dashboard</title>
    <link rel="stylesheet" href="index.css" type="text/css" media="screen" charset="utf-8">
    <style type="text/css">
      #graphite{
        stroke-width: 2px;
      }

      .span4{
        text-align:center;
      }
      </style>
  </head>
  <body>
    <div id="dashboard" class="container-fluid">
      <div class="row-fluid">
        <div id="g1-1" class="span4">
        </div>
        <div id="g1-2" class="span4">
        </div>
        <div id="g1-3" class="span4">
        </div>
      </div>

      <div class="row-fluid">
        <div id="g2-1" class="span4">
        </div>
        <div id="g2-2" class="span4">
        </div>
        <div id="g2-3" class="span4">
        </div>
      </div>

      <div class="row-fluid">
        <div id="g3-1" class="span4">
        </div>
        <div id="g3-2" class="span4">
        </div>
        <div id="g3-3" class="span4">
        </div>
      </div>
    </div>

    <script type="text/javascript" src="jquery-1.7.1.min.js"></script>
    <script type="text/javascript" src="underscore.js"></script>
    <script type="text/javascript" src="backbone.js"></script>
    <script type="text/javascript" src="d3.js"></script>
    <script type="text/javascript" src="d3.gauge.js"></script>
    <script type="text/javascript" src="graphene.js"></script>
    <script type="text/javascript">
(function() {
  var labelFormatter = function(label) {
    if (label) {
      var parts = label.split(/\./);
      return parts[parts.length-1];
    } else {
      return label;
    }
  };
  var config = <?php echo $config; ?>;
  var containers = <?php echo $containers; ?>;

  var description = {};
  for (var i=0; i < config.length; i++) {
      description[config[i]["name"]] = {"source": config[i]["target"],
          TimeSeries: {
              parent: containers[i],
              title: config[i]["name"],
              label_formatter: labelFormatter
          }
      }
  }

  var g = new Graphene;
  g.build(description);


}).call(this);
    </script>


  </body>
</html>
