<?php

define('WIKIPEDIA_SUBJECT_TFA', 0); // Today's featured article
define('WIKIPEDIA_SUBJECT_DYK', 1); // Did you know...

class WikipediaBridge extends BridgeAbstract {
	const MAINTAINER = 'logmanoriginal';
	const NAME = 'Wikipedia bridge for many languages';
	const URI = 'https://www.wikipedia.org/';
	const DESCRIPTION = 'Returns articles for a language of your choice';

	const PARAMETERS = array( array(
		'language'=>array(
			'name'=>'Language',
			'type'=>'list',
			'required'=>true,
			'title'=>'Select your language',
			'exampleValue'=>'English',
			'values'=>array(
				'English'=>'en',
				'Dutch'=>'nl',
				'Esperanto'=>'eo',
				'French'=>'fr',
				'German'=>'de',
			)
		),
		'subject'=>array(
			'name'=>'Subject',
			'type'=>'list',
			'required'=>true,
			'title'=>'What subject are you interested in?',
			'exampleValue'=>'Today\'s featured article',
			'values'=>array(
				'Today\'s featured article'=>'tfa',
				'Did you know…'=>'dyk'
			)
		),
		'fullarticle'=>array(
			'name'=>'Load full article',
			'type'=>'checkbox',
			'title'=>'Activate to always load the full article'
		)
	));

	public function getURI(){
		return 'https://' . strtolower($this->getInput('language')) . '.wikipedia.org';
    }

	public function getName(){
		switch($this->getInput('subject')){
			case 'tfa':
				$subject = WIKIPEDIA_SUBJECT_TFA;
				break;
			case 'dyk':
				$subject = WIKIPEDIA_SUBJECT_DYK;
				break;
			default:
				$subject = WIKIPEDIA_SUBJECT_TFA;
				break;
		}

		switch($subject){
			case WIKIPEDIA_SUBJECT_TFA:
				$name = 'Today\'s featured article from ' . strtolower($this->getInput('language')) . '.wikipedia.org';
				break;
			case WIKIPEDIA_SUBJECT_DYK:
				$name = 'Did you know? - articles from ' . strtolower($this->getInput('language')) . '.wikipedia.org';
				break;
			default:
				$name = 'Articles from ' . strtolower($this->getInput('language')) . '.wikipedia.org';
				break;
		}
        return $name;
    }

	public function collectData(){

		switch($this->getInput('subject')){
			case 'tfa':
				$subject = WIKIPEDIA_SUBJECT_TFA;
				break;
			case 'dyk':
				$subject = WIKIPEDIA_SUBJECT_DYK;
				break;
			default:
				$subject = WIKIPEDIA_SUBJECT_TFA;
				break;
		}

		$fullArticle = $this->getInput('fullarticle');

		// This will automatically send us to the correct main page in any language (try it!)
		$html = getSimpleHTMLDOM($this->getURI() . '/wiki');

		if(!$html)
			returnServerError('Could not load site: ' . $this->getURI() . '!');

		/*
		* Now read content depending on the language (make sure to create one function per language!)
		* We build the function name automatically, just make sure you create a private function ending
		* with your desired language code, where the language code is upper case! (en -> GetContentsEN).
		*/
		$function = 'GetContents' . strtoupper($this->getInput('language'));

		if(!method_exists($this, $function))
			returnServerError('A function to get the contents for your language is missing (\'' . $function . '\')!');

		/*
		* The method takes care of creating all items.
		*/
		$this->$function($html, $subject, $fullArticle);
	}

	/**
	* Replaces all relative URIs with absolute ones
	* @param $element A simplehtmldom element
	* @return The $element->innertext with all URIs replaced
	*/
	private function ReplaceURIInHTMLElement($element){
		return str_replace('href="/', 'href="' . $this->getURI() . '/', $element->innertext);
	}

	/*
	* Adds a new item to $items using a generic operation (should work for most (all?) wikis)
	* $anchorText can be specified if the wiki in question doesn't use '...' (like Dutch, French and Italian)
	* $anchorFallbackIndex can be used to specify a different fallback link than the first (e.g., -1 for the last)
	*/
	private function AddTodaysFeaturedArticleGeneric($element, $fullArticle, $anchorText = '...', $anchorFallbackIndex = 0){
		// Clean the bottom of the featured article
		if ($element->find('div', -1))
			$element->find('div', -1)->outertext = '';

		// The title and URI of the article can be found in an anchor containing the string '...' in most wikis ('full article ...')
		$target = $element->find('p/a', $anchorFallbackIndex);
		foreach($element->find('//a') as $anchor){
			if(strpos($anchor->innertext, $anchorText) !== false){
				$target = $anchor;
				break;
			}
		}

		$item = array();
		$item['uri'] = $this->getURI() . $target->href;
		$item['title'] = $target->title;

		if(!$fullArticle)
			$item['content'] = strip_tags($this->ReplaceURIInHTMLElement($element), '<a><p><br><img>');
		else
			$item['content'] = $this->LoadFullArticle($item['uri']);

		$this->items[] = $item;
	}

	/*
	* Adds a new item to $items using a generic operation (should work for most (all?) wikis)
	*/
	private function AddDidYouKnowGeneric($element, $fullArticle){
		foreach($element->find('ul', 0)->find('li') as $entry){
			$item = array();

			// We can only use the first anchor, there is no way of finding the 'correct' one if there are multiple
			$item['uri'] = $this->getURI() . $entry->find('a', 0)->href;
			$item['title'] = strip_tags($entry->innertext);

			if(!$fullArticle)
				$item['content'] = $this->ReplaceURIInHTMLElement($entry);
			else
				$item['content'] = $this->LoadFullArticle($item['uri']);

			$this->items[] = $item;
		}
	}

	/**
	* Loads the full article from a given URI
	*/
	private function LoadFullArticle($uri){
		$content_html = getSimpleHTMLDOMCached($uri);

		if(!$content_html)
			returnServerError('Could not load site: ' . $uri . '!');

		$content = $content_html->find('#mw-content-text', 0);

		if(!$content)
			returnServerError('Could not find content in page: ' . $uri . '!');

		// Let's remove a couple of things from the article
		$table = $content->find('#toc', 0); // Table of contents
		if(!$table === false)
			$table->outertext = '';

		foreach($content->find('ol.references') as $reference) // References
			$reference->outertext = '';

		return str_replace('href="/', 'href="' . $this->getURI() . '/', $content->innertext);
	}

	/**
	* Implementation for de.wikipedia.org
	*/
	private function GetContentsDE($html, $subject, $fullArticle){
		switch($subject){
			case WIKIPEDIA_SUBJECT_TFA:
				$element = $html->find('div[id=mf-tfa]', 0);
				$this->AddTodaysFeaturedArticleGeneric($element, $fullArticle);
				break;
			case WIKIPEDIA_SUBJECT_DYK:
				$element = $html->find('div[id=mf-dyk]', 0);
				$this->AddDidYouKnowGeneric($element, $fullArticle);
				break;
			default:
				break;
		}
	}

	/**
	* Implementation for fr.wikipedia.org
	*/
	private function GetContentsFR($html, $subject, $fullArticle){
		switch($subject){
			case WIKIPEDIA_SUBJECT_TFA:
				$element = $html->find('div[id=accueil-lumieresur]', 0);
				$this->AddTodaysFeaturedArticleGeneric($element, $fullArticle, 'Lire la suite');
				break;
			case WIKIPEDIA_SUBJECT_DYK:
				$element = $html->find('div[id=SaviezVous]', 0);
				$this->AddDidYouKnowGeneric($element, $fullArticle);
				break;
			default:
				break;
		}
	}

	/**
	* Implementation for en.wikipedia.org
	*/
	private function GetContentsEN($html, $subject, $fullArticle){
		switch($subject){
			case WIKIPEDIA_SUBJECT_TFA:
				$element = $html->find('div[id=mp-tfa]', 0);
				$this->AddTodaysFeaturedArticleGeneric($element, $fullArticle);
				break;
			case WIKIPEDIA_SUBJECT_DYK:
				$element = $html->find('div[id=mp-dyk]', 0);
				$this->AddDidYouKnowGeneric($element, $fullArticle);
				break;
			default:
				break;
		}
	}

	/**
	* Implementation for eo.wikipedia.org
	*/
	private function GetContentsEO($html, $subject, $fullArticle){
		switch($subject){
			case WIKIPEDIA_SUBJECT_TFA:
				$element = $html->find('div[id=mf-artikolo-de-la-semajno]', 0);
				$this->AddTodaysFeaturedArticleGeneric($element, $fullArticle);
				break;
			case WIKIPEDIA_SUBJECT_DYK:
				$element = $html->find('div[id=mw-content-text]', 0)->find('table', 4)->find('td', 4);
				$this->AddDidYouKnowGeneric($element, $fullArticle);
				break;
			default:
				break;
		}
	}

	/**
	* Implementation for nl.wikipedia.org
	*/
	private function GetContentsNL($html, $subject, $fullArticle){
		switch($subject){
			case WIKIPEDIA_SUBJECT_TFA:
				$element = $html->find('div[id=mf-uitgelicht]', 0);
				$this->AddTodaysFeaturedArticleGeneric($element, $fullArticle, 'Lees meer');
				break;
			case WIKIPEDIA_SUBJECT_DYK:
				$element = $html->find('div[id=mw-content-text]', 0)->find('table', 4)->find('td', 2);
				$this->AddDidYouKnowGeneric($element, $fullArticle);
				break;
			default:
				break;
		}
	}
}