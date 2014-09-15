<?php

$dir = "/var/lib/phpfiwa/";

function usage($scriptname)
{
  print $scriptname ." usage: \n";
  print $scriptname ." -i feedid -n minvalue -x maxvalue\n";
}

// arguments parameters
$opts = getopt("i:n:x:");

// Handle command line arguments
foreach (array_keys($opts) as $opt)
  switch ($opt)
  {
    // feed id
    case 'i': $id = $opts['i'];  break;
    // min value
    case 'n': $min = $opts['n']; break;
    // max value
    case 'x': $max = $opts['x']; break;
  }

// check all parameters are good
if (isset($id) && isset($max) && isset($min))
{
  print "Feed ID   : ".$id  ."\n";
  print "Min value : ".$min ."\n";
  print "Max value : ".$max ."\n";
}
// Error display usage
else
{
  usage($argv[0]);
  exit(1);
}

for ($n=0; $n<4; $n++)
{
    if (file_exists($dir.$id."_$n.dat"))
    {
        $npoints = floor(filesize($dir.$id."_$n.dat") / 4.0);
        $fh = fopen($dir.$id."_$n.dat","c+");

        for ($i=0; $i<$npoints; $i++)
        {
            $tmp = unpack("f",fread($fh,4));
            $val = $tmp[1];

            if (!is_nan($val))
            {
                if ($val>$max)
                {
                    echo $val." > ".$max ."\n";
                    fseek($fh,$i*4);
                    fwrite($fh,pack("f",NAN));
                }

                if ($val<$min)
                {
                    echo $val." < ".$min . "\n";
                    fseek($fh,$i*4);
                    fwrite($fh,pack("f",NAN));
                }

            }
        }

        fclose($fh);
    }
}
