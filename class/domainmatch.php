<?php

class DomainMatch
{
    protected $domains = array();
    
    function __construct($file=NULL)
    {
        if ($file) {            
            if (!$this->importFile($file)) throw new Exception("Unable to import domains from file $file");
        }
    }
    
    function importFile($file)
    {
        $key = "DomainMatch.$file";
        
        if (function_exists('apc_fetch') && ($res = apc_fetch($key)))
        {
            $this->domains = unserialize($res);
            return true;
        }
                
        $lines = @file($file); if (!$lines) return false;
        
        foreach($lines as $line)
        {
            $line = trim(preg_replace('!^\s*(?:https?://)?([a-z0-9.-]*)(?:\s*\#.*)?!','\1',$line));
            if (!$line) continue;
            $this->add($line);
        }
        
        if (function_exists('apc_store'))
        {
            apc_store($key,serialize($this->domains), 3600*3);
        }
        
        return true;
    }
    
    function add($domain)
    {
        $domain = explode('.',trim($domain,'.'));
        $this->addArray($this->domains,$domain);
    }
    
    protected function addArray(&$to, &$from)
    {
        $key = array_pop($from);
        if (!isset($to[$key])) $to[$key] = array();
        if (count($from)) $this->addArray($to[$key],$from);
    }
    
    function check($uri)
    {
        $parts = explode('.',trim($uri,'.'));
        
        return $this->checkPart($this->domains, $parts);
    }
    
    protected function checkPart(array $in, array &$what, $level=0)
    {
        if (!count($what)) {d($in,'no what? at '.$level);return NULL;}
        
        $key = array_pop($what);
        if (!isset($in[$key])) {d($key,"part not found"); return $level;}
        return $this->checkPart($in[$key],$what,$level+1);
    }
}
