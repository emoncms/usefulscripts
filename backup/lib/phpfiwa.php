<?php

function import_phpfiwa($id,$server,$apikey,$datadir)
{   
    echo "PHPFIWA: $id\n";
    
    // Download phpfiwa feed meta data
    $remote_meta = json_decode(file_get_contents($server."/feed/getmeta.json?apikey=$apikey&id=".$id));
    
    // Load local meta data file
    if (file_exists($datadir.$id.".meta"))
    {
        $local_meta = new stdClass();
        if (!$metafile = @fopen($datadir.$id.".meta", 'rb')) {
            echo "Cannot open local meta data file\n";
            return false;
        }
        
        fseek($metafile,4);
        
        $tmp = unpack("I",fread($metafile,4)); 
        $local_meta->start_time = $tmp[1];
        $tmp = unpack("I",fread($metafile,4)); 
        $local_meta->nlayers = $tmp[1];
       
        fseek($metafile,4 * $local_meta->nlayers);
        
        $local_meta->interval = array();
        for ($i=0; $i<$local_meta->nlayers; $i++)
        {
          $tmp = unpack("I",fread($metafile,4)); 
          $local_meta->interval[$i] = $tmp[1];
        }
        
        fclose($metafile);
    } else {
        $local_meta = $remote_meta;
        
        if (!$metafile = @fopen($datadir.$id.".meta", 'wb')) {
            echo "Cannot open local meta data file: Does the phpfiwa folder exist?\n";
            return false;
        }
        fwrite($metafile,pack("I",0));
        fwrite($metafile,pack("I",$remote_meta->start_time)); 
        fwrite($metafile,pack("I",$remote_meta->nlayers));
        foreach ($remote_meta->npoints as $n) fwrite($metafile,pack("I",0)); // Legacy
        foreach ($remote_meta->interval as $d) fwrite($metafile,pack("I",$d));
        fclose($metafile);
    }
    
    // We now check if the local meta data is the same as the remote meta data.
    // Given that the starttime, the interval and the feedname is the same we assume
    // that we are dealing with the same feed
    if ($local_meta->start_time == $remote_meta->start_time && $local_meta->interval[0] == $remote_meta->interval[0])
    {
        for ($layer=0; $layer<$local_meta->nlayers; $layer++)
        {
            echo "--layer: $layer ";

            if (file_exists($datadir.$id."_".$layer.".dat")) {
                $downloadfrom = filesize($datadir.$id."_".$layer.".dat");
                if (intval($downloadfrom/4.0)!=($downloadfrom/4.0)) { 
                    echo "PHPFiwa feed ".$id." corrupt\n"; 
                    die; 
                }
            } else { 
                $downloadfrom = 0;
            }

            $url = $server."/feed/export.json?apikey=$apikey&id=$id&layer=$layer&start=$downloadfrom";
            if (!$primary = @fopen( $url, 'r' )) {
                echo "Failed to access remote server\n";
                return false;
            }

            if ($downloadfrom>=4) {
                // update last datapoint
                $firstdp = fread($primary,4);
                if (!$backup = @fopen($datadir.$id."_".$layer.".dat", 'c')) {
                    echo "Cannot open local data file\n";
                    return false;
                }
                fseek($backup,$downloadfrom-4);
                fwrite($backup,$firstdp);
                fclose($backup);
            }
        
            if (!$backup = @fopen($datadir.$id."_".$layer.".dat", 'a')) {
                echo "Cannot open local data file\n";
                return false;
            }
        
            $dnsize = 0;
            if ($primary)
            {
                for (;;)
                {
                    $data = fread($primary,8192);
                    $p1 = ftell($backup);
                    fwrite($backup,$data);
                    $dnsize += ftell($backup) - $p1;
                    if (feof($primary)) break;
                }
            }

            fclose($backup);
            fclose($primary);
        
            echo $dnsize." bytes\n";
        }
    }
    else
    {
      echo "local and remote meta data do not match\n";
    }
}
