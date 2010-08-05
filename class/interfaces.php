<?php

interface ISblamServices
{
    /**
     * @return PDO
     */
    function getDB();

    /**
     * @return ISblamHttp
     */
    function getHTTP();
}

interface ISblam
{
	function testPost(ISblamPost $p);
	function testTrackback(ISblamTrackback $t);

	function addTest(ISblamTest $t, $phase);
}

interface ISblamPost
{
	/** return post content as it has been posted, except you must convert its encoding to UTF-8 if your site uses different one */
	function getRawContent();

	/** signature is user-configurable text that is added to every post (used usually on forums) */
	function getSignature();

	/** all links from message body, together with their labels. don't append author's uri nor signature (there are separate methods for it)
			returns array of SblamURI elements
	    @return array */
	function getLinks();

	/** return plain UTF-8 encoded text that has no formatting and all links (including link labels) removed (to avoid skewing penalty for link text).
			@return string
  */
	function getText();

	/**	display name of the post author. No HTML. UTF-8 encoded. NULL if not known.
			@return string
	*/
	function getAuthorName();

	/**	e-mail as reported by author of the post, NULL if not known.
			@return string
	*/
	function getAuthorEmail();

	/**	homepage as reported by author of the post, NULL if not known.
			@return string
	*/
	function getAuthorURI();

	/** ip of the machine that has sent the post (REMOTE_ADDR). string format (ipv4 dot-notation)
		@return string
	*/
	function getAuthorIP();

	/**	array of the IPs known to send/relay (proxies) the post with first item being the same as returned by getAuthorIP().
		@return array
	*/
	function getAuthorIPs();

	/** misc. information about the post. all optional
			\li published - when post was published, in time() format
			\li updated - when post was updated, in time() format, can't be before published date
			\li lastcomment - when last comment to this post was published, in time() format

			@return array
	*/
	function getDates();


	/** timestamp when comment was posted - usually returns time(), but you should use it just in case posts are tested retroactively

		@return int
	*/
	function getPostTime();


	/* get 2-letter symbol of post's primary language. NULL if mixed/not known/doesn't matter.
			@return string
	function getLanguage();*/

	/* return associative array of lowercased http headers present when entry was posted, e.g. array('header-name'=>'value');
			@return array
	function getHTTPHeaders(); */

	/* if you track visitors using cookies and/or session IDs, report it using this method.
	    @return number of pages known to be requested by this poster (int) or true (bool) if session does exist, but has unknown length or false (bool) when no valid session initiated (no cookie, session id posted)

	function getSessionLength();*/

	/** convert post to HTML fragment (<html>/<head>/<body> tags are not allowed). Must use UTF-8 encoding, don't add <meta>.
			@return string
	*/
	// function getHTML();

}

interface ISblamTrackback
{
	/** return associative array with trackback information, as per specification.
			\li title - title of the pinging entry
			\li excerpt - quoted fragment of the entry
			\li url - url of the pinging entry
			\li blog_name - name of pinging blog

			exception from trackback specification is that all strings must be UTF-8

			@see http://www.sixapart.com/pronet/docs/trackback_spec
	*/
	function getTrackbackInfo();

	/** post that trackback refers to, NULL if not known.
			@return ISblamPost */
	function getReferredPost();
}

interface ISblamTest
{
	const CERTAINITY_LOW = 0.5;
	const CERTAINITY_NORMAL = 0.75;
	const CERTAINITY_HIGH = 1;
	const CERTAINITY_SURE = 2;

	static function info();

	public function __construct(array $settings, ISblamServices $services);
}

interface ISblamTestPost extends ISblamTest
{
	/** perform full check and return array with three elements:
			\li 0 (probability) - how big chance is that this message is spam (or not spam). negative=not spam, positive=spam. scalar (float) between 0 and 1; 0 = dunno, ±0.5 = maybe, ±1 = surely.
			\li 1 (certainity) - how certain is that method; use predefined constants.
			\li 2 name of the test (should be constant) - to track accuracy, may be used for bayesian filtering
	*/
	function testPost(ISblamPost $p);

	/** FYI post that will be passed to testPost later. This allows launching tests asynchronously. */
	function preTestPost(ISblamPost $p);


	// notify about final score (post,score,cert) - for auto blacklists, bayes
	// notify about moderated post (basepost, t/f) - for reporting/correcting mistakes
}

interface ISblamTestTrackback extends ISblamTest
{
	/** @see ISblamTestPost::testPost() */
	function testTrackback(ISblamTrackback $t);
}
