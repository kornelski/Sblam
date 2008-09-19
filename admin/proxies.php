<?php

class ProxiesPage extends AdminPage
{
    function index()
    {
        return array(
            'proxies'=>$this->getPDO()->query("/*maxtime10*/SELECT t.host,count(r.host) as ipcnt FROM trustedproxies t LEFT JOIN dnscache r ON r.host = t.host GROUP BY t.host ORDER BY t.host")->fetchAll(PDO::FETCH_ASSOC),
        );        
    }
    
    function post_index()
    {
        if (!empty($_POST['remove']))
        {
            $this->getPDO()->prepareExecute("DELETE FROM trustedproxies WHERE host = ?",array($_POST['remove']));
        }
        elseif (!empty($_POST['add']))
        {
            $sblam = $this->getSblam();
            if (SblamURI::gethostbyname($_POST['add']))
            {
                $this->getPDO()->prepareExecute("INSERT INTO trustedproxies(host) VALUES(?)",array($_POST['add']));
                $_POST['add']='';
            }
            else throw new Exception($_POST['add'].' does not resolve');
        }
        return $this->index();
    }
    
    private function lookup(array $hosts)
    {
        $sblam = $this->getSblam(); // init SblamURI
        foreach($hosts as $h)
        {
            d(SblamURI::gethostbyname($h['host']),$h['host']);
        }
    }
    
    function post_cache()
    {
        switch($_POST['type'])
        {
            case 'insecure':
                $this->getPDO()->exec("/*maxtime30*/INSERT INTO dnscache (host,ip) SELECT t.host,r.ip FROM trustedproxies t LEFT JOIN dnscache d ON d.host = t.host INNER JOIN dnscache r ON t.host = r.host WHERE d.host IS NULL");
                break;
            case 'missing':
                $this->lookup($this->getPDO()->query("/*maxtime20*/SELECT t.host FROM trustedproxies t LEFT JOIN dnscache r ON t.host = r.host WHERE r.host IS NULL")->fetchAll(PDO::FETCH_ASSOC));
                break;
            default:
                $this->lookup($this->getPDO()->query("/*maxtime20*/SELECT t.host FROM trustedproxies t")->fetchAll(PDO::FETCH_ASSOC));
                break;                
        }
        die();
        return array('redirect'=>'proxies');
    }
}
