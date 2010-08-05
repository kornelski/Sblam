<?php

class SblamTestChallenge extends SblamTestPost
{
    const CACHE_MAXAGE = 3600;
    const TZ_TOLERANCE = 43200;

    private function getChallengeData(ISblamPost $p)
    {
        if ($installid = $p->getInstallId())
        {
            // !!! must be in sync with challenge.js.php
            $fieldname = 'sc'.abs(crc32($installid));

            $post = $p->getPost();
            if (!empty($post[$fieldname]) && preg_match('!^([a-f0-9]{32})([a-f0-9]+;([a-f0-9]+);([\d.,]+))((?:;\d+)*)$!',$post[$fieldname],$r))
            {
                return array($installid, $fieldname, $r[1], $r[2], hexdec($r[3]), explode(',',$r[4]), explode(';',$r[5]));
            }
            d("can't find expected field $fieldname for challenge");

            foreach($post as $k => $v)
            {
                if (preg_match('/^sc\d+$/',$k))
                {
                    d("Found different install id for challenge");
                    return array(0,$k,0,0,0,array(),array());
                }
            }
        }
        else d("challenge: didn't get install id");

        return NULL;
    }

    function testPost(ISblamPost $p)
    {
        $r = $this->getChallengeData($p);
        if (!$r) return NULL;

        list($installid,$fieldname,$hash,$signed,$challengetime,$postips,$posts) = $r;

        if (!$installid && !$hash) return array(-0.001, self::CERTAINITY_LOW, "JS: invalid install id $fieldname");

        $score=1;
        $writetime = '?';
        $age=0;
        if ($hash === md5($installid . $signed))
        {
            $score++;

            $age = $p->getPostTime() - $challengetime;

            if ($age < 7) {d('quick to write'); $score -= 3;}            // filled-in too fast
            elseif ($age > 20 && $age < 600) $score += 2;       // just right
            elseif ($age < self::CACHE_MAXAGE) $score++;        // a little old

            $authorips = $p->getAuthorIPs();

            if ($postips[0] === $authorips[0]) $score += 4; // same IP, excellent
            elseif (ip2long($postips[0]) >> 8 === ip2long($authorips[0]) >> 8)  // at least subnet must match (allows proxy farms)
            {
                $score++;
                for($i=1; $i < count($postips); $i++) if (in_array($postips[$i], $authorips)) {d('forwarded host matches');$score++; break;}
            }
            else $score-=2;

            if (!empty($posts[1]) && $posts[1] > $challengetime - self::TZ_TOLERANCE)
            {
                $score++;
                if (count($posts)>2 && $posts[count($posts)-1] > $posts[1])
                {
                    $writetime = $posts[count($posts)-1] - $posts[1];

                    if ($writetime < 4) {d('less than 3 sec to write a post');$score -= 3;}
                    else {d($writetime,'perfect');$score++;}
                }
            }
        }
        else $score = -3;

        if ($score < 0) return array(-$score/5, self::CERTAINITY_LOW, "Forged JS challenge ($score)");
        if ($score > 0) return array(-$score/11, self::CERTAINITY_NORMAL, "Successful JS challenge ($score; age $age; write $writetime)");

        return NULL; // there are too many ways in which JS challenge can fail, so can't penalize for that!
    }

    static function info()
    {
        return array(
            'name'=>'JavaScript challenge',
            'desc'=>'Give green flag to clients (properly) supporting JavaScript',
            'remote'=>false,
        );
    }
}
