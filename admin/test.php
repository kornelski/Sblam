<?php

require_once "class/sblam.php";
require_once "class/sblamtest.php";
require_once "class/sblampost.php";
require_once "class/server.php";
require_once "admin/postformatter.php";

class TestPage extends AdminPage
{

    function index()
    {
        return array();
    }

    function post_index()
    {
        $base = $this->getSblamBase();

        $config = Server::getDefaultConfig();

        $config['throttle']['enabled'] = '0'; // FIXME: this should be handled within plugins
        $config['linksleeve']['enabled'] = '0';
        $config['dupes']['enabled'] = '0';

        $sblam = new Sblam($config, $this->services);

        $num = !empty($_POST['num']) ? intval($_POST['num']) : 100;
        foreach($this->services->getDB()->query("SELECT id FROM posts_meta WHERE spamscore IS NULL and spamcert IS NULL ORDER BY rand() LIMIT
$num")->fetchAll(PDO::FETCH_ASSOC) as $r)
        {
            $score = $sblam->testPost($base->getPostById($r['id']));

        	$this->services->getDB()->prepareExecute("UPDATE posts_meta SET spamscore=?,spamcert=? WHERE id=?",array(round($score[0]*100),round($score[1]*100),$r['id']));
        	$this->services->getDB()->prepareExecute("UPDATE posts_data SET spamreason=? WHERE id=?",array($score[2],$r['id']));
        }
    }

    function id($id)
    {
        $base = $this->getSblamBase();
        if (!($post = $base->getPostById($id))) throw new Exception("No post $id");

        $score = $this->test($post);
        if ($score)
        {
            $post->setSpamScore($score);
            $post->setSpamReason($score[2]);
        }

        return array(
            'title'=>'Tested',
            'score'=>$score,
            'post'=>$post,
            );
    }

    protected function test(ISblamPost $post)
    {
        $sblam = $this->getSblam();
        $score = $sblam->testPost($post);
        return $score;
    }

}

