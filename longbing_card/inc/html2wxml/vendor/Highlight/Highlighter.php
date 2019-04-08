<?php  namespace Highlight;
class Highlighter 
{
	private $modeBuffer = "";
	private $result = "";
	private $top = NULL;
	private $language = NULL;
	private $keywordCount = 0;
	private $relevance = 0;
	private $ignoreIllegals = false;
	private static $classMap = array( );
	private static $languages = NULL;
	private static $aliases = NULL;
	private $tabReplace = NULL;
	private $classPrefix = "hljs-";
	private $autodetectSet = array( "xml", "json", "javascript", "css", "php", "http" );
	public function __construct() 
	{
		$this->registerLanguages();
	}
	private function registerLanguages() 
	{
		foreach( array( "xml", "django", "javascript", "matlab", "cpp" ) as $l ) 
		{
			$this->createLanguage($l);
		}
		$d = dir(__DIR__ . DIRECTORY_SEPARATOR . "languages");
		while( false !== ($entry = $d->read()) ) 
		{
			if( $entry[0] !== "." ) 
			{
				$lng = substr($entry, 0, -5);
				$this->createLanguage($lng);
			}
		}
		$d->close();
		self::$languages = array_keys(self::$classMap);
	}
	private function createLanguage($languageId) 
	{
		if( !isset(self::$classMap[$languageId]) ) 
		{
			self::registerLanguage($languageId, __DIR__ . DIRECTORY_SEPARATOR . "languages" . DIRECTORY_SEPARATOR . (string) $languageId . ".json");
		}
		return self::$classMap[$languageId];
	}
	public static function registerLanguage($languageId, $absoluteFilePath) 
	{
		$lang = new Language($languageId, $absoluteFilePath);
		self::$classMap[$languageId] = $lang;
		if( isset($lang->mode->aliases) ) 
		{
			foreach( $lang->mode->aliases as $alias ) 
			{
				self::$aliases[$alias] = $languageId;
			}
		}
		return self::$classMap[$languageId];
	}
	private function testRe($re, $lexeme) 
	{
		if( !$re ) 
		{
			return false;
		}
		$test = preg_match($re, $lexeme, $match, PREG_OFFSET_CAPTURE);
		if( $test === false ) 
		{
			throw new \Exception("Invalid regexp: " . var_export($re, true));
		}
		return count($match) && $match[0][1] == 0;
	}
	private function subMode($lexeme, $mode) 
	{
		for( $i = 0; $i < count($mode->contains);
		$i++ ) 
		{
			if( $this->testRe($mode->contains[$i]->beginRe, $lexeme) ) 
			{
				return $mode->contains[$i];
			}
		}
	}
	private function endOfMode($mode, $lexeme) 
	{
		if( $this->testRe($mode->endRe, $lexeme) ) 
		{
			while( $mode->endsParent && $mode->parent ) 
			{
				$mode = $mode->parent;
			}
			return $mode;
		}
		if( $mode->endsWithParent ) 
		{
			return $this->endOfMode($mode->parent, $lexeme);
		}
	}
	private function isIllegal($lexeme, $mode) 
	{
		return !$this->ignoreIllegals && $this->testRe($mode->illegalRe, $lexeme);
	}
	private function keywordMatch($mode, $match) 
	{
		$kwd = ($this->language->caseInsensitive ? mb_strtolower($match[0], "UTF-8") : $match[0]);
		return (isset($mode->keywords[$kwd]) ? $mode->keywords[$kwd] : null);
	}
	private function buildSpan($classname, $insideSpan, $leaveOpen = false, $noPrefix = false) 
	{
		$classPrefix = ($noPrefix ? "" : $this->classPrefix);
		$openSpan = "<span class=\"" . $classPrefix;
		$closeSpan = ($leaveOpen ? "" : "</span>");
		$openSpan .= $classname . "\">";
		return $openSpan . $insideSpan . $closeSpan;
	}
	private function escape($value) 
	{
		return htmlspecialchars($value, ENT_NOQUOTES);
	}
	private function processKeywords() 
	{
		if( empty($this->top->keywords) ) 
		{
			return $this->escape($this->modeBuffer);
		}
		$result = "";
		$lastIndex = 0;
		if( $this->top->lexemesRe ) 
		{
			while( preg_match($this->top->lexemesRe, $this->modeBuffer, $match, PREG_OFFSET_CAPTURE, $lastIndex) ) 
			{
				$result .= $this->escape(substr($this->modeBuffer, $lastIndex, $match[0][1] - $lastIndex));
				$keyword_match = $this->keywordMatch($this->top, $match[0]);
				if( $keyword_match ) 
				{
					$this->relevance += $keyword_match[1];
					$result .= $this->buildSpan($keyword_match[0], $this->escape($match[0][0]));
				}
				else 
				{
					$result .= $this->escape($match[0][0]);
				}
				$lastIndex = strlen($match[0][0]) + $match[0][1];
			}
		}
		return $result . $this->escape(substr($this->modeBuffer, $lastIndex));
	}
	private function processSubLanguage() 
	{
		try 
		{
			$hl = new Highlighter();
			$hl->autodetectSet = $this->autodetectSet;
			$explicit = is_string($this->top->subLanguage);
			if( $explicit && !isset(array_flip(self::$languages)[$this->top->subLanguage]) ) 
			{
				return $this->escape($this->modeBuffer);
			}
			if( $explicit ) 
			{
				$res = $hl->highlight($this->top->subLanguage, $this->modeBuffer, true, (isset($this->continuations[$this->top->subLanguage]) ? $this->continuations[$this->top->subLanguage] : null));
			}
			else 
			{
				$res = $hl->highlightAuto($this->modeBuffer, (count($this->top->subLanguage) ? $this->top->subLanguage : null));
			}
			if( 0 < $this->top->relevance ) 
			{
				$this->relevance += $res->relevance;
			}
			if( $explicit ) 
			{
				$this->continuations[$this->top->subLanguage] = $res->top;
			}
			return $this->buildSpan($res->language, $res->value, false, true);
		}
		catch( \Exception $e ) 
		{
			error_log("TODO, is this a relevant catch?");
			error_log($e);
			return $this->escape($this->modeBuffer);
		}
	}
	private function processBuffer() 
	{
		return (!is_null($this->top->subLanguage) ? $this->processSubLanguage() : $this->processKeywords());
	}
	private function startNewMode($mode, $lexeme) 
	{
		$markup = ($mode->className ? $this->buildSpan($mode->className, "", true) : "");
		if( $mode->returnBegin ) 
		{
			$this->result .= $markup;
			$this->modeBuffer = "";
		}
		else 
		{
			if( $mode->excludeBegin ) 
			{
				$this->result .= $this->escape($lexeme) . $markup;
				$this->modeBuffer = "";
			}
			else 
			{
				$this->result .= $markup;
				$this->modeBuffer = $lexeme;
			}
		}
		$t = clone $mode;
		$t->parent = $this->top;
		$this->top = $t;
	}
	private function processLexeme($buffer, $lexeme = NULL) 
	{
		$this->modeBuffer .= $buffer;
		if( null === $lexeme ) 
		{
			$this->result .= $this->processBuffer();
			return 0;
		}
		$new_mode = $this->subMode($lexeme, $this->top);
		if( $new_mode ) 
		{
			$this->result .= $this->processBuffer();
			$this->startNewMode($new_mode, $lexeme);
			return ($new_mode->returnBegin ? 0 : strlen($lexeme));
		}
		$end_mode = $this->endOfMode($this->top, $lexeme);
		if( $end_mode ) 
		{
			$origin = $this->top;
			if( !($origin->returnEnd || $origin->excludeEnd) ) 
			{
				$this->modeBuffer .= $lexeme;
			}
			$this->result .= $this->processBuffer();
			do 
			{
				if( $this->top->className ) 
				{
					$this->result .= "</span>";
				}
				$this->relevance += $this->top->relevance;
				$this->top = $this->top->parent;
			}
			while( $this->top != $end_mode->parent );
			if( $origin->excludeEnd ) 
			{
				$this->result .= $this->escape($lexeme);
			}
			$this->modeBuffer = "";
			if( $end_mode->starts ) 
			{
				$this->startNewMode($end_mode->starts, "");
			}
			return ($origin->returnEnd ? 0 : strlen($lexeme));
		}
		if( $this->isIllegal($lexeme, $this->top) ) 
		{
			$className = ($this->top->className ? $this->top->className : "unnamed");
			$err = "Illegal lexeme \"" . $lexeme . "\" for mode \"" . $className . "\"";
			throw new \Exception($err);
		}
		$this->modeBuffer .= $lexeme;
		$l = strlen($lexeme);
		return ($l ? $l : 1);
	}
	private function replaceTabs($code) 
	{
		if( $this->tabReplace !== null ) 
		{
			return str_replace("\t", $this->tabReplace, $code);
		}
		return $code;
	}
	public function setAutodetectLanguages(array $set) 
	{
		$this->autodetectSet = array_unique($set);
		$this->registerLanguages();
	}
	public function getTabReplace() 
	{
		return $this->tabReplace;
	}
	public function setTabReplace($tabReplace) 
	{
		$this->tabReplace = $tabReplace;
	}
	public function getClassPrefix() 
	{
		return $this->classPrefix;
	}
	public function setClassPrefix($classPrefix) 
	{
		$this->classPrefix = $classPrefix;
	}
	private function getLanguage($name) 
	{
		if( isset(self::$classMap[$name]) ) 
		{
			return self::$classMap[$name];
		}
		if( isset(self::$aliases[$name]) && isset(self::$classMap[self::$aliases[$name]]) ) 
		{
			return self::$classMap[self::$aliases[$name]];
		}
		throw new \DomainException("Unknown language: " . $name);
	}
	public function highlight($language, $code, $ignoreIllegals = true, $continuation = NULL) 
	{
		$this->language = $this->getLanguage($language);
		$this->language->compile();
		$this->top = ($continuation ? $continuation : $this->language->mode);
		$this->continuations = array( );
		$this->result = "";
		$current = $this->top;
		while( $current != $this->language->mode ) 
		{
			if( $current->className ) 
			{
				$this->result = $this->buildSpan($current->className, "", true) . $this->result;
			}
			$current = $current->parent;
		}
		$this->modeBuffer = "";
		$this->relevance = 0;
		$this->ignoreIllegals = $ignoreIllegals;
		$res = new \stdClass();
		$res->relevance = 0;
		$res->value = "";
		$res->language = "";
		try 
		{
			$match = null;
			$count = 0;
			$index = 0;
			while( $this->top && $this->top->terminators ) 
			{
				$test = preg_match($this->top->terminators, $code, $match, PREG_OFFSET_CAPTURE, $index);
				if( $test === false ) 
				{
					throw new \Exception("Invalid regExp " . var_export($this->top->terminators, true));
				}
				if( $test === 0 ) 
				{
					break;
				}
				$count = $this->processLexeme(substr($code, $index, $match[0][1] - $index), $match[0][0]);
				$index = $match[0][1] + $count;
			}
			$this->processLexeme(substr($code, $index));
			$current = $this->top;
			while( $current != $this->language->mode ) 
			{
				if( $current->className ) 
				{
					$this->result .= "</span>";
				}
				$current = $current->parent;
			}
			$res->relevance = $this->relevance;
			$res->value = $this->replaceTabs($this->result);
			$res->language = $this->language->name;
			$res->top = $this->top;
			return $res;
		}
		catch( \Exception $e ) 
		{
			if( strpos($e->getMessage(), "Illegal") !== false ) 
			{
				$res->value = $this->escape($code);
				return $res;
			}
			throw $e;
		}
	}
	public function highlightAuto($code, $languageSubset = NULL) 
	{
		$res = new \stdClass();
		$res->relevance = 0;
		$res->value = $this->escape($code);
		$res->language = "";
		$scnd = clone $res;
		$tmp = ($languageSubset ? $languageSubset : $this->autodetectSet);
		foreach( $tmp as $l ) 
		{
			$current = $this->highlight($l, $code, false);
			if( $scnd->relevance < $current->relevance ) 
			{
				$scnd = $current;
			}
			if( $res->relevance < $current->relevance ) 
			{
				$scnd = $res;
				$res = $current;
			}
		}
		if( $scnd->language ) 
		{
			$res->secondBest = $scnd;
		}
		return $res;
	}
	public function listLanguages() 
	{
		return self::$languages;
	}
}
?>