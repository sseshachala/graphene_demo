<?php

$GRAPHITE_ROOT = "http://ec2-184-73-92-62.compute-1.amazonaws.com";

function make_graphite_url($targets, $from="-2hours") {
    global $GRAPHITE_ROOT;
    $query = "from=$from&until=now&format=json";
    foreach ($targets as $t) {
        $query .= "&target=$t";
    }
    return $GRAPHITE_ROOT."/render?".$query;
}

function plot_memory($path) {
    $targets = array($path."memory-buffered",
        $path."memory-cached",
        $path."memory-free",
        $path."memory-used");
    return make_graphite_url($targets);
}

function plot_load($path) {
    $targets = array($path."load.shortterm", $path."load.midterm", $path."load.longterm");
    return make_graphite_url($targets);
}

function get_json_response($url, $postdata) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    $response = curl_exec( $ch );
    return json_decode($response);
}

function do_graphite_find($query) {
    global $GRAPHITE_ROOT;
    $url = $GRAPHITE_ROOT . "/metrics/find/";
    $data = array("query" => $query,
                  "format" => "completer");
    return get_json_response($url, $data);
}

function get_users() {
    $users = do_graphite_find("");
    $ret = array();
    foreach ($users->metrics as $u) {
        array_push($ret, $u->name);
    }
    return $ret;
}

function get_servers($user) {
    $servers = do_graphite_find("$user.");
    $ret = array();
    foreach ($servers->metrics as $u) {
        array_push($ret, $u->name);
    }
    return $ret;
}

function get_plugins($user, $server) {
    $plugins = do_graphite_find("$user.$server.");
    return $plugins;
}

$clientuser = $_GET["user"];
$clienthost = $_GET["host"];

if ($clientuser && $clienthost) {
    // Show all services for this machine on this client
    $plugins = get_plugins($clientuser, $clienthost);
    foreach ($plugins->metrics as $p) {
        if ($p->name == "load") {
            $load_target = plot_load($p->path);
        } else if ($p->name == "memory") {
            $memory_target = plot_memory($p->path);
        }
    }

} else if ($clientuser && !$clienthost) {
    // We've selected a user, show all hosts
    $available_hosts = get_servers($clientuser);
    echo "<h2>Customer: $clientuser</h2>\n";
    echo "<h2>Available servers</h2>\n";
    foreach ($available_hosts as $host) {
        echo "<li><a href=\"?user=$clientuser&host=$host\">$host</a></li>\n";
    }

} else {
    // No user selected, show all users
    $available_users = get_users();
    echo "<h2>Available customers</h2>\n";
    foreach ($available_users as $user) {
        echo "<li><a href=\"?user=$user\">$user</a></li>\n";
    }
}

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
    </div>

    <script type="text/javascript" src="jquery-1.7.1.min.js"></script>
    <script type="text/javascript" src="index.js"></script>
    <script type="text/javascript">
(function() {
  var description;
  description = {
    "Load": {
        source: "<?php echo $load_target; ?>",
      TimeSeries: {
        parent: '#g1-1'
      }
    },
    "Memory": {
        source: "<?php echo $memory_target; ?>",
      TimeSeries: {
        parent: '#g2-1'
      }
    }
  };

  var g = new Graphene;
  g.build(description);


}).call(this);
    </script>


  </body>
</html>
