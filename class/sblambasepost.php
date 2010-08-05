<?php

require_once "class/sblampost.php";

class SblamBasePost extends SblamPost
{
	protected $reason;
	function setSpamReason($r) {$this->reason = $r;}
	function getSpamReason() {return $this->reason;}

	protected $spam_score = array();
	function setSpamScore(array $s)
	{
	    assert('is_numeric($s[0])');
	    assert('is_numeric($s[1]) && $s[1]>=0');
	    $this->spam_score = $s;
	}

	/**
	 * @return array(score, certainity) in scale 0-1
	 */
	function getSpamScore() {return $this->spam_score;}

	protected $post_id;
	function setPostId($p) {$this->post_id = $p;}
	function getPostId() {return $this->post_id;}
}
