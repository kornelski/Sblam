<?php

class SblamHTTP implements ISblamHTTP
{
    private $host,$path='/',$method='GET', $content_type, $payload, $timeout=5;

    function setPost($payload, $content_type)
    {
        $this->method = "POST";
        $this->content_type = $content_type;
        $this->payload = $payload;
        return $this;
    }

    function setPostForm(array $payload)
    {
        return $this->setPost(http_build_query($payload,'_','&'),'application/x-www-form-urlencoded');
    }

    function setPath($path, array $query_string = array())
    {
        if (!$path || $path[0] !== '/') throw new Exception("Invalid path $path");
        $this->path = $path;

        if (count($query_string))
        {
            $this->path .= '?'.http_build_query($query_string,'_','&');
        }
        return $this;
    }

    function setHost($host)
    {
        $this->host = $host; return $this;
    }

    function setTimeout($timeout)
    {
        if (!is_numeric($timeout)) throw new Exception("Invalid timeout $timeout");

        $this->timeout = $timeout; return $this;
    }

    function requestAsync()
    {
		$postdata = $this->method." ".$this->path." HTTP/1.0\r\n".
		            "Host: ".$this->host."\r\n".
                    "User-Agent: PHP/5 Sblam/1\r\n".
				    "Connection:close\r\n";

		if ($this->method === "POST")
		{
		    $postdata .= "Content-Type: ".$this->content_type."\r\n".
                         "Content-Length: ".strlen($this->payload). "\r\n".
                         "\r\n".$this->payload;
		}
		else
		{
		    $postdata .= "\r\n";
	    }
	    d($postdata,"Sending request");
		$fp=@fsockopen($this->host, 80, $err1, $err2, $this->timeout);
		if (!$fp) {
		    warn('cant connect');return NULL;
		}
		stream_set_timeout($fp, $this->timeout);

		if (!fwrite($fp, $postdata, strlen($postdata))) {
		    warn('cant upload');return NULL;
		}

		return new SblamHTTPAsyncResponse($fp);
    }
}

class SblamHTTPAsyncResponse implements ISblamHTTPAsyncResponse
{
    private $fp, $body, $status, $headers;

    function __construct($fp)
    {
        $this->fp = $fp;
    }

    private function readResponse()
    {
        $res='';
		while(!feof($this->fp) && false !== ($data=fread($this->fp, 8000)))
		{
			$res .= $data;
		}
		fclose($this->fp); $this->fp = NULL;

	d($res,"got response");
        if (preg_match('/^HTTP\/1\.\d (\d+)[^\n]*\n(.*)\r?\n\r?\n(.*)$/s',$res,$m))
        {
            $this->status = $m[1];
            $this->headers = $m[2];
            $this->body = $m[3];
        }
	else warn($res,"Can't parse");
    }

    function getStatus()
    {
        if ($this->fp) $this->readResponse();
        return $this->status;
    }

    function getResponseBody()
    {
        if ($this->fp) $this->readResponse();
        return $this->body;
    }
}
