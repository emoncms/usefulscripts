<?php

  /*
  All Emoncms code is released under the GNU Affero General Public License.
  See COPYRIGHT.txt and LICENSE.txt.

  ---------------------------------------------------------------------
  Emoncms - open source energy visualisation
  Part of the OpenEnergyMonitor project:
  http://openenergymonitor.org
  */

?>

<link href="bootstrap/css/bootstrap.css" rel="stylesheet">

<br>
<div class="container">

<h3>Account monitor</h3>
<table class="table">

<?php

  $users = array(
    array("name"=>"User1", "apikey"=>"User1apikey"),
    array("name"=>"User2", "apikey"=>"User2apikey")
  );

  foreach ($users as $user)
  {
    $feeds = json_decode(file_get_contents("http://emoncms.org/feed/list.json?apikey=".$user['apikey']));
    
    $active = 0;
    foreach ($feeds as $feed) {

      $timeupdated = (time() - $feed->time/1000);
      if ($timeupdated<3600) $active++;
    }
    $total = count($feeds);

    echo "<tr><td>".$user['name']."</td><td>";
    if ($active==$total) {
      echo '<span class="label label-success">'.$active."/".$total."</span>";
    } elseif ($active>0) {
      echo '<span class="label label-warning">'.$active."/".$total."</span>";
    } else {
      echo '<span class="label label-important">'.$active."/".$total."</span>";
    }
    echo "</td></tr>";
  }

?>

</table>
</div>
