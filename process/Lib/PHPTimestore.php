<?php

// This timeseries engine implements:
// Fixed Interval No Averaging

class PHPTimestore
{
    private $dir = "/var/lib/timestore/";
    private $log;
    
    private $buffers = array();
    private $metadata_cache = array();
    
    private $filehandle = array();
    private $dpposition = array();
    
    /**
     * Constructor.
     *
     * @api
    */

    public function __construct($settings)
    {
        if (isset($settings['datadir'])) $this->dir = $settings['datadir'];
        
        $this->log = new EmonLogger(__FILE__);
    }
    
    public function get_meta($feedid)
    {
        $feedid = (int) $feedid;
        $metafile = $this->dir.str_pad($feedid, 16, '0', STR_PAD_LEFT).".tsdb";
        $datafile = $this->dir.str_pad($feedid, 16, '0', STR_PAD_LEFT)."_0_.dat";
        
        // Load metadata from cache if it exists
        if (isset($this->metadata_cache[$feedid])) 
        {
            return $this->metadata_cache[$feedid];
        }
        elseif (file_exists($metafile))
        {
            $meta = new stdClass();
            $meta->feedid = $feedid;
            $size = filesize($metafile);
            $fh = fopen($metafile, 'rb');

            fseek($fh,8);
            $d = fread($fh,8);
            $tmp = unpack("h*",$d);
            //$meta->feedid = (int) strrev($tmp[1]);
            $tmp = unpack("I",fread($fh,4));
            $meta->nmetrics = $tmp[1];
            $tmp = unpack("I",fread($fh,4));
            // $legacy_npoints = $tmp[1];
            $tmp = unpack("I",fread($fh,8));
            $meta->start_time = $tmp[1];
            $tmp = unpack("I",fread($fh,4));
            $meta->interval = $tmp[1];
            fclose($fh);
            
            // Double verification of npoints
            clearstatcache($datafile);
            $filesize = filesize($datafile);
            $meta->npoints = floor($filesize / 4.0);
            
            return $meta;
        }
        else
        {
            return false;
        }
    }
    
    public function readnext($feedid)
    {
        $datafile = $this->dir.str_pad($feedid, 16, '0', STR_PAD_LEFT)."_0_.dat";
        if (!isset($this->filehandle[$feedid])) {
            $this->filehandle[$feedid] = fopen($datafile, 'rb');
            $this->dpposition[$feedid] = 0;
        }
        
        $fh = $this->filehandle[$feedid];
        if (feof($fh)) return false;
        
        $meta = $this->get_meta($feedid);

        $d = fread($fh,4);
        if (strlen($d)!=4) return false;
        
        $val = unpack("f",$d);
        $value = $val[1];
        
        $time = $meta->start_time + $this->dpposition[$feedid] * $meta->interval;
        $this->dpposition[$feedid] += 1;
        
        return array('time'=>$time, 'value'=>$value);
    }
}

