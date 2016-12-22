<?php
//Based on https://github.com/mitsukarenai/twitterbridge-noapi
class TwitterBridge extends BridgeAbstract{

    private $request;

	public function loadMetadatas() {

		$this->maintainer = "mitsukarenai";
		$this->name = "Twitter Bridge";
		$this->uri = "http://twitter.com/";
		$this->description = "Returns user timelines or keyword/hashtag search results (without using their API).";
		$this->update = "2014-05-25";

		$this->parameters["By keyword or hashtag"] =
		'[
			{
				"name" : "Keyword or #hashtag",
				"identifier" : "q"
			}
		]';

		$this->parameters["By username"] =
		'[
			{
				"name" : "username",
				"identifier" : "u"
			}
		]';

	}

    public function collectData(array $param){
        $html = '';
        $author = '<unknown>';
        if (isset($param['q'])) {   /* keyword search mode */
            $this->request = $param['q'];
            $html = file_get_html('https://twitter.com/search?q='.urlencode($this->request).'&f=tweets') or $this->returnError('No results for this query.', 404);
        }
        elseif (isset($param['u'])) {   /* user timeline mode */
            $this->request = $param['u'];
            $author = $this->request;
            $html = file_get_html('http://twitter.com/'.urlencode($this->request)) or $this->returnError('Requested username can\'t be found.', 404);
        }
        else {
            $this->returnError('You must specify a keyword (?q=...) or a Twitter username (?u=...).', 400);
        }

        foreach($html->find('div.js-stream-tweet') as $tweet) {
            $item = new \Item();
            $item->username = $tweet->getAttribute('data-screen-name');	// extract username and sanitize
            $item->fullname = $tweet->getAttribute('data-name'); // extract fullname (pseudonym)
            $item->avatar = $tweet->find('img', 0)->src;	// get avatar link
            $item->id = $tweet->getAttribute('data-tweet-id');	// get TweetID
            $item->uri = 'https://twitter.com'.$tweet->find('a.js-permalink', 0)->getAttribute('href');	// get tweet link
            $item->timestamp = $tweet->find('span.js-short-timestamp', 0)->getAttribute('data-time');	// extract tweet timestamp
            $item->author = $author;

            //xmaux72: media thumbnail added
            foreach($tweet->find('div.AdaptiveMedia-photoContainer') as $photo) {
                $item->thumbnailUri = $photo->getAttribute('data-image-url');
            }

            // processing content links
            foreach($tweet->find('a') as $link) {
                if($link->hasAttribute('data-expanded-url') ) {
                    $link->href = $link->getAttribute('data-expanded-url');
                }
                $link->removeAttribute('data-expanded-url');
                $link->removeAttribute('data-query-source');
                $link->removeAttribute('rel');
                $link->removeAttribute('class');
                $link->removeAttribute('target');
                $link->removeAttribute('title');
            }

            $item->content = str_replace('href="/', 'href="https://twitter.com/', strip_tags($tweet->find('p.js-tweet-text', 0)->innertext, '<a>'));	// extract tweet text
            $item->title = $item->fullname . ' (@' . $item->username . ') | ' . strip_tags($item->content);
            $this->items[] = $item;
        }
    }

    public function getName(){
        return (!empty($this->request) ? $this->request .' - ' : '') .'Twitter Bridge';
    }

    public function getURI(){
        return 'http://twitter.com';
    }

    public function getCacheDuration(){
        return 300; // 5 minutes
    }
}
