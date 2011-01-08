<?php
require_once "class/interfaces.php";
require_once "class/sblam.php";
require_once "class/sblamtest.php";
require_once "class/sblampost.php";
require_once "tests/bayes.php";
require_once "tests/dedupe.php";

/**
 * this is bad and must die
 */
class UpdatebayesPage extends AdminPage
{
    function index()
    {
        // back compat
        if (false !== strpos($_SERVER['HTTP_USER_AGENT'],"Wget"))
        {
            return $this->post_index();
        }
        return array();
    }

    function ping()
    {
        apc_store('update_active',time());
    }

    function is_active()
    {
        return (apc_fetch('update_active') > time()-240);
    }

    function post_index($max=12500, $batchsize = 300)
    {
        if ($this->is_active()) die("Another process is active!");

        $this->ping();

        ignore_user_abort(true);

        $batchsize = max(5,intval($batchsize));

        $pdo = $this->services->getDB();
        $pdo->exec("truncate bayeswordsh_s");
        $pdo->exec("truncate linkswordsh_s");

        $base = new SblamBase($pdo);

        $bayes = new SblamTestBayes(array(), $this->services);

        $done = 0;
        $failures = 0;
        $wait = 0;
        $maxspam = 600;
        while($max--)
        {
            $this->ping();
            $sort = (rand()&64) ? 'DESC':'';
            $doneinbatch = 0;
            foreach($pdo->query("/*maxtime20*/SELECT id FROM posts_meta
                WHERE (added IS NULL OR added = 0) AND (manualspam IS NOT NULL OR (abs(spamscore)>20 AND spamcert > 90)) AND spamscore < $maxspam
                ORDER BY id $sort LIMIT $batchsize")->fetchAll(PDO::FETCH_ASSOC) as $res)
            {
                $doneinbatch++;
                $post = $base->getPostById($res['id']);
                if (!$post) {$failures++; warn($res['id'],"Can't find post");  continue;}

                $poststarttime = microtime(true);

                $this->ping();

                list($spamscore,$spamcert) = $post->getSpamScore();
                $howmuch=1;
                if (!$post->bayesadded && ($post->manualspam !== NULL || abs($spamscore)>0.9))
                {
                    $isspam = ($post->manualspam!==NULL)?$post->manualspam:($spamscore>0?1:0);

                    if (($post->manualspam!==NULL && $post->manualspam==0) || $spamscore < -2.5)
                    {
                        $howmuch=3;
                    }

                    if (!$bayes->addPost($post, $isspam, $howmuch)) {$failures++;warn("Failed to add post ".$post->getPostId()); continue;}

                    if (!$pdo->exec("/*maxtime15*/UPDATE posts_meta set added=1$howmuch
                                                    WHERE (added=0 or added is null) and id= '".addslashes($post->getPostId())."'"))
                    {
                        warn($post->getPostId(),"update of post failed");
                        break;
                    }
                }
                $done++;

                $postchecktime = microtime(true) - $poststarttime;

                $load = sys_getloadavg();
                $load = max($load[0]-0.4,$load[1]/2,$load[2]/3,0);

                if ($load < 1) $load /= 3;
                elseif ($load >= 2.2) $load *= 3;

                $load = max($load,$postchecktime);

                $wait += $load;
                $this->ping();

        		echo "#$done; $failures fail; id{$res['id']}; score {$spamscore} * $howmuch; load {$load}; wait ".round(0.1*$wait,1)."\n<br>"; flush();
                usleep(100000 * $load);
            }

            if (!$doneinbatch)
            {
                sleep(5); $maxspam += 40 + $maxspam/10;
                if ($maxspam > 1500) break;
            }
            else if ($maxspam > 400)
            {
                $maxspam -= 10;
            }
        }

        return array(
            'done'=>$done,
            'failed'=> $failures,
            'waited'=>round(0.1*$wait),
            'waitperpost' => $done ? round(0.1*$wait/($done),2) : 0,
        );
    }
}
