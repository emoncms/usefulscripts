<?php

// This timeseries engine implements:
// Fixed Interval No Averaging

class PHPTimeSeries
{
    private $dir = "/var/lib/phptimeseries/";
    private $log;
    
    private $filehandle = array();
    
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
    
    public function readnext($id)
    {
        if (!isset($this->filehandle[$id])) {
            $this->filehandle[$id] = fopen($this->dir."feed_$id.MYD", 'rb');
        }
        $fh = $this->filehandle[$id];
        if (feof($fh)) return false;
        
        $d = fread($fh,9);
        if (strlen($d)!=9) return false;
        
        $array = unpack("x/Itime/fvalue",$d);
        
        return array('time'=>$array['time'], 'value'=>$array['value']);
    }
}

