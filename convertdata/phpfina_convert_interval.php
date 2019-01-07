<?php

define('EMONCMS_EXEC', 1);

$log_enabled = false;
require "Lib/EmonLogger.php";
require "Lib/PHPFina.php";

$dir = "/var/lib/phpfina/";

function usage($scriptname)
{
  print $scriptname ." usage: \n";
  print $scriptname ." -i <feed id> -n <new interval>\n";
}

$opts = getopt("i:n:");

foreach (array_keys($opts) as $opt)
  switch ($opt) {
    // feed id
    case 'i': $feed_id = $opts['i'];  break;
    // new interval
    case 'n': $new_interval = $opts['n']; break;
  }

if (isset($feed_id) && isset($new_interval)) {
  print "Feed ID      : ".$feed_id  ."\n";
  print "New interval : ".$new_interval ."\n";
} else {
  usage($argv[0]);
  exit(1);
}

$new_feed_id = -$feed_id;

if (!file_exists($dir.$feed_id.".dat") || !file_exists($dir.$feed_id.".meta")) {
  print "Couldn't find data for that feed!\n";
  usage($argv[0]);
  exit(1);
}
if (file_exists($dir.$new_feed_id.".dat") || file_exists($dir.$new_feed_id.".meta")) {
  print "Existing temporary feed exists for this feed! Did you already run this?\n";
  print "Try deleting the temporary file(s) -- `" . $dir . $new_feed_id . ".(dat|meta)` and try again.\n";
  exit(1);
}

$engine = new PHPFina(array("datadir" => $dir));

$meta = $engine->get_meta($feed_id);
$old_interval = $meta->interval;
$npoints = $engine->get_npoints($feed_id);

print "Old interval : ".$old_interval ."\n";
print "Total points : ".$npoints ."\n";

if ($old_interval > $new_interval) {
  print "Don't support shrinking the interval yet!\n";
  exit(1);
}

$points_processed = 0;
$interval_factor = $new_interval / $old_interval;

if (!$engine->create($new_feed_id, array("interval" => $new_interval))) {
  print "Failed to create new feed.\n";
  exit(1);
}

while ($points_processed < $npoints) {
  $to_process = $interval_factor;
  if ($points_processed + $to_process > $npoints) {
    $to_process = $npoints - $points_processed;
  }

  $start_point = floor($points_processed);
  $end_point = floor($points_processed + $to_process - 1);

  $start = ($meta->start_time + ($old_interval * $start_point)) * 1000;
  $end = ($meta->start_time + ($old_interval * $end_point)) * 1000;

  print "Processing " . ($end_point - $start_point + 1) . " points from " . $start . " to " . $end . "\n";

  $data = $engine->get_data($feed_id, $start, $end, $old_interval);
  $count = count($data);
  if ($count > 0) {
    $total = 0;
    foreach ($data as $point) {
      $total += $point[1];
    }
    $average = $total / count($data);

    $timestamp = $start / 1000;
    print "New point : {time = " . $timestamp . ", value = " . $average . "}\n";
    $engine->prepare($new_feed_id, $timestamp, $average, false);
  } else {
    print "Skipping point at time " . $start . " as there was no data.\n";
  }

  $points_processed += $to_process;
}

$engine->save();


if (
  !rename($dir.$feed_id.".dat", $dir.$feed_id."-old.dat") ||
  !rename($dir.$feed_id.".meta", $dir.$feed_id."-old.meta") ||
  !rename($dir.$new_feed_id.".dat", $dir.$feed_id.".dat") ||
  !rename($dir.$new_feed_id.".meta", $dir.$feed_id.".meta")
) {
  print "Uh oh! Failed to replace old feed files with new feed files!\n";
  exit(1);
}
