<?php

// This timeseries engine implements:
// Fixed Interval No Averaging

class PHPFiwa
{
    private $dir = "/var/lib/phpfiwa/";
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
    
    public function get_meta($filename)
    {
        // Load metadata from cache if it exists
        if (isset($this->metadata_cache[$filename])) 
        {
            return $this->metadata_cache[$filename];
        }
        elseif (file_exists($this->dir.$filename.".meta"))
        {
            $meta = new stdClass();
            $meta->id = $filename;
        
            $metafile = fopen($this->dir.$filename.".meta", 'rb');

            $tmp = unpack("I",fread($metafile,4));
            $tmp = unpack("I",fread($metafile,4)); 
            $meta->start_time = $tmp[1];
            $tmp = unpack("I",fread($metafile,4)); 
            $meta->nlayers = $tmp[1];
            
            for ($i=0; $i<$meta->nlayers; $i++) {
              $tmp = unpack("I",fread($metafile,4)); 
            }
            
            $meta->interval = array();
            for ($i=0; $i<$meta->nlayers; $i++)
            {
              $tmp = unpack("I",fread($metafile,4)); 
              $meta->interval[$i] = $tmp[1];
            }
            fclose($metafile);
            
            // Save to metadata_cache so that we dont need to open the file next time
            $this->metadata_cache[$filename] = $meta;
            
            return $meta;
        }
        else
        {
            return false;
        }
    }
    
    public function readnext($filename)
    {
        if (!isset($this->filehandle[$filename])) {
            $this->filehandle[$filename] = fopen($this->dir.$filename."_0.dat", 'rb');
            $this->dpposition[$filename] = 0;
        }
        $fh = $this->filehandle[$filename];
        if (feof($fh)) return false;
        
        $meta = $this->get_meta($filename);

        $d = fread($fh,4);
        if (strlen($d)!=4) return false;
        
        $val = unpack("f",$d);
        $value = $val[1];
        
        $time = $meta->start_time + $this->dpposition[$filename] * $meta->interval[0];
        $this->dpposition[$filename] += 1;
        
        return array('time'=>$time, 'value'=>$value);
    }
}

