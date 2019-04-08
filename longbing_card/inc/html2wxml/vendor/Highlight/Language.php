<?php  namespace Highlight;
class Language 
{
	public $caseInsensitive = false;
	public $aliases = NULL;
	public function complete(&$e) 
	{
		if( !isset($e) ) 
		{
			$e = new \stdClass();
		}
		$patch = array( "begin" => true, "end" => true, "lexemes" => true, "illegal" => true );
		$def = array( "begin" => "", "beginRe" => "", "beginKeywords" => "", "excludeBegin" => "", "returnBegin" => "", "end" => "", "endRe" => "", "endsParent" => "", "endsWithParent" => "", "excludeEnd" => "", "returnEnd" => "", "starts" => "", "terminators" => "", "terminatorEnd" => "", "lexemes" => "", "lexemesRe" => "", "illegal" => "", "illegalRe" => "", "className" => "", "contains" => array( ), "keywords" => null, "subLanguage" => null, "subLanguageMode" => "", "compiled" => false, "relevance" => 1 );
		foreach( $patch as $k => $v ) 
		{
			if( isset($e->$k) ) 
			{
				$e->$k = str_replace("\\/", "/", $e->$k);
				$e->$k = str_replace("/", "\\/", $e->$k);
			}
		}
		foreach( $def as $k => $v ) 
		{
			if( !isset($e->$k) ) 
			{
				$e->$k = $v;
			}
		}
	}
	public function __construct($lang, $filePath) 
	{
		$json = file_get_contents($filePath);
		$this->mode = json_decode($json);
		$this->name = $lang;
		$this->aliases = (isset($this->mode->aliases) ? $this->mode->aliases : null);
		$this->caseInsensitive = (isset($this->mode->case_insensitive) ? $this->mode->case_insensitive : false);
	}
	private function langRe($value, $global = false) 
	{
		return "/" . $value . "/um" . (($this->caseInsensitive ? "i" : ""));
	}
	private function processKeyWords($kw) 
	{
		if( is_string($kw) ) 
		{
			if( $this->caseInsensitive ) 
			{
				$kw = mb_strtolower($kw, "UTF-8");
			}
			$kw = array( "keyword" => explode(" ", $kw) );
		}
		else 
		{
			foreach( $kw as $cls => $vl ) 
			{
				if( !is_array($vl) ) 
				{
					if( $this->caseInsensitive ) 
					{
						$vl = mb_strtolower($vl, "UTF-8");
					}
					$kw->$cls = explode(" ", $vl);
				}
			}
		}
		return $kw;
	}
	private function compileMode($mode, $parent = NULL) 
	{
		if( isset($mode->compiled) ) 
		{
			return NULL;
		}
		$this->complete($mode);
		$mode->compiled = true;
		$mode->keywords = ($mode->keywords ? $mode->keywords : $mode->beginKeywords);
		if( $mode->keywords && !is_array($mode->keywords) ) 
		{
			$compiledKeywords = array( );
			$mode->lexemesRe = $this->langRe(($mode->lexemes ? $mode->lexemes : "\\b\\w+\\b"), true);
			foreach( $this->processKeyWords($mode->keywords) as $clsNm => $dat ) 
			{
				if( !is_array($dat) ) 
				{
					$dat = array( $dat );
				}
				foreach( $dat as $kw ) 
				{
					$pair = explode("|", $kw);
					$compiledKeywords[$pair[0]] = array( $clsNm, (isset($pair[1]) ? intval($pair[1]) : 1) );
				}
			}
			$mode->keywords = $compiledKeywords;
		}
		if( $parent ) 
		{
			if( $mode->beginKeywords ) 
			{
				$mode->begin = "\\b(" . implode("|", explode(" ", $mode->beginKeywords)) . ")\\b";
			}
			if( !$mode->begin ) 
			{
				$mode->begin = "\\B|\\b";
			}
			$mode->beginRe = $this->langRe($mode->begin);
			if( !$mode->end && !$mode->endsWithParent ) 
			{
				$mode->end = "\\B|\\b";
			}
			if( $mode->end ) 
			{
				$mode->endRe = $this->langRe($mode->end);
			}
			$mode->terminatorEnd = $mode->end;
			if( $mode->endsWithParent && $parent->terminatorEnd ) 
			{
				$mode->terminatorEnd .= (($mode->end ? "|" : "")) . $parent->terminatorEnd;
			}
		}
		if( $mode->illegal ) 
		{
			$mode->illegalRe = $this->langRe($mode->illegal);
		}
		$expanded_contains = array( );
		for( $i = 0; $i < count($mode->contains);
		$i++ ) 
		{
			if( isset($mode->contains[$i]->variants) ) 
			{
				foreach( $mode->contains[$i]->variants as $v ) 
				{
					$x = (object) ((array) $v + (array) $mode->contains[$i]);
					unset($x->variants);
					$expanded_contains[] = $x;
				}
			}
			else 
			{
				$expanded_contains[] = ("self" === $mode->contains[$i] ? $mode : $mode->contains[$i]);
			}
		}
		$mode->contains = $expanded_contains;
		for( $i = 0; $i < count($mode->contains);
		$i++ ) 
		{
			$this->compileMode($mode->contains[$i], $mode);
		}
		if( $mode->starts ) 
		{
			$this->compileMode($mode->starts, $parent);
		}
		$terminators = array( );
		for( $i = 0; $i < count($mode->contains);
		$i++ ) 
		{
			$terminators[] = ($mode->contains[$i]->beginKeywords ? "\\.?(" . $mode->contains[$i]->begin . ")\\.?" : $mode->contains[$i]->begin);
		}
		if( $mode->terminatorEnd ) 
		{
			$terminators[] = $mode->terminatorEnd;
		}
		if( $mode->illegal ) 
		{
			$terminators[] = $mode->illegal;
		}
		$mode->terminators = (count($terminators) ? $this->langRe(implode("|", $terminators), true) : null);
	}
	public function compile() 
	{
		if( !isset($this->mode->compiled) ) 
		{
			$jr = new JsonRef();
			$this->mode = $jr->decode($this->mode);
			$this->compileMode($this->mode);
		}
	}
}
?>