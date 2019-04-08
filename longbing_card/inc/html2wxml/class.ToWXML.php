<?php  class ToWXML 
{
	private $args = NULL;
	public $highlight = NULL;
	public $parsedown = NULL;
	private $block_tags = "br,address,article,applet,aside,audio,blockquote,canvas,center,dd,dir,div,dl,dt,fieldset,figcaption,figure,footer,form,frameset,h1,h2,h3,h4,h5,h6,header,hgroup,hr,iframe,isindex,li,map,menu,noframes,noscript,object,ol,output,p,pre,section,script,table,tbody,td,tfoot,th,thead,tr,ul,video";
	private $inline_tags = "a,abbr,acronym,applet,b,basefont,bdo,big,button,cite,del,dfn,em,font,i,iframe,img,input,ins,kbd,label,map,object,q,s,samp,script,select,small,span,strike,strong,sub,sup,textarea,tt,u,var";
	private $img_idx = NULL;
	private $ol_idx = NULL;
	private $li_idx = NULL;
	public function __construct() 
	{
		require_once("vendor/Highlight/Autoloader.php");
		spl_autoload_register("Highlight\\Autoloader::load");
		$this->highlight = new Highlight\Highlighter();
		require_once("vendor/Parsedown/Parsedown.php");
		$this->parsedown = new Parsedown();
		$this->block_tags = explode(",", $this->block_tags);
		$this->inline_tags = explode(",", $this->inline_tags);
	}
	public function init() 
	{
		$this->img_idx = 0;
		$this->ol_idx = 0;
		$this->li_idx = array( );
	}
	public function towxml($text, $args = array( )) 
	{
		$this->init();
		$this->args = $this->param_atts(array( "type" => "html", "highlight" => true, "linenums" => true, "imghost" => NULL, "encode" => true, "highlight_languages" => array( "html", "js", "php", "css" ) ), $args);
		$this->highlight->setAutodetectLanguages($this->args["highlight_languages"]);
		if( $this->args["type"] == "html" ) 
		{
			return $this->html2json($text);
		}
		if( in_array($this->args["type"], array( "markdown", "md" )) ) 
		{
			return $this->markdown2json($text);
		}
		return false;
	}
	public function html2json($html) 
	{
		$return = $this->html2array("<body>" . $html . "</body>");
		return ($this->args["encode"] ? json_encode($return, JSON_UNESCAPED_UNICODE) : $return);
	}
	public function markdown2json($text) 
	{
		$html = $this->parsedown->text($text);
		return $this->html2json($html);
	}
	public function html2array($html, $remove_line_break = true) 
	{
		$html = mb_convert_encoding($html, "HTML-ENTITIES", "UTF-8");
		$dom = new DOMDocument();
		@$dom->loadHTML($html);
		$dom->encoding = "UTF-8";
		$json = $this->element2array($dom->documentElement, $remove_line_break);
		return (isset($json["nodes"][0]["nodes"]) ? $json["nodes"][0]["nodes"] : array( ));
	}
	public function element2array($element, $remove_line_break = true) 
	{
		$tag = $element->tagName;
		$type = NULL;
		$ol_idx = $this->ol_idx;
		if( in_array($tag, $this->block_tags) ) 
		{
			$type = "block";
		}
		else 
		{
			if( in_array($tag, $this->inline_tags) ) 
			{
				$type = "inline";
			}
			else 
			{
				$type = "inline";
			}
		}
		$obj = array( "tag" => $tag );
		if( $type != NULL ) 
		{
			$obj = array_merge($obj, array( "type" => $type ));
		}
		if( $tag == "img" ) 
		{
			$obj["idx"] = $this->img_idx;
			$this->img_idx++;
		}
		else 
		{
			if( $tag == "ol" ) 
			{
				$ol_idx++;
				$this->li_idx[$ol_idx] = 0;
			}
			else 
			{
				if( $tag == "li" ) 
				{
					if( $element->parentNode->tagName == "ol" ) 
					{
						$obj["idx"] = $this->li_idx[$ol_idx]++;
					}
				}
				else 
				{
					if( $tag == "pre" ) 
					{
						if( $this->args["highlight"] === true || $this->args["linenums"] === true ) 
						{
							$code = $element->childNodes[0]->textContent;
							if( $this->args["highlight"] === true ) 
							{
								$obj["attr"]["class"] = (isset($obj["attr"]["class"]) ? $obj["attr"]["class"] . " hljs" : "hljs");
								$r = $this->highlight->highlightAuto($code);
								$code = $r->value;
							}
							if( $this->args["linenums"] === true ) 
							{
								$code = "<ol><li>" . str_replace("\n", "</li><li>", $code) . "</li></ol>";
							}
							$obj["nodes"] = $this->html2array("<body>" . $code . "</body>", false);
							return $obj;
						}
					}
					else 
					{
						if( in_array($tag, array( "script", "style" )) ) 
						{
							return NULL;
						}
					}
				}
			}
		}
		foreach( $element->attributes as $attribute ) 
		{
			if( $element->tagName == "img" && $attribute->name == "src" && !is_null($this->args["imghost"]) ) 
			{
				$obj["attr"][$attribute->name] = $this->args["imghost"] . $attribute->value;
			}
			else 
			{
				$obj["attr"][$attribute->name] = $attribute->value;
			}
		}
		foreach( $element->childNodes as $sub_element ) 
		{
			if( $sub_element->nodeType == XML_TEXT_NODE ) 
			{
				$text = ($remove_line_break && $tag != "pre" ? str_replace(array( "\n", "\r" ), array( "", "" ), $sub_element->wholeText) : $sub_element->wholeText);
				if( $tag == "ol" || $tag == "ul" ) 
				{
					$text = trim($text);
				}
				if( $text != "" ) 
				{
					$obj["nodes"][] = array( "tag" => "#text", "text" => $text );
				}
			}
			else 
			{
				if( $sub_element->nodeType == XML_CDATA_SECTION_NODE ) 
				{
					$text = ($remove_line_break && $tag != "pre" ? str_replace(array( "\n", "\r" ), array( "", "" ), $sub_element->data) : $sub_element->data);
					$obj["nodes"][] = array( "tag" => "#text", "text" => $text );
				}
				else 
				{
					if( $sub_element->nodeType == XML_ELEMENT_NODE ) 
					{
						$this->ol_idx = $ol_idx;
						$node = $this->element2array($sub_element, $remove_line_break);
						if( is_array($node) ) 
						{
							$obj["nodes"][] = $node;
						}
					}
				}
			}
		}
		return $obj;
	}
	public function param_atts($pairs, $atts) 
	{
		$atts = (array) $atts;
		$out = array( );
		foreach( $pairs as $name => $default ) 
		{
			if( array_key_exists($name, $atts) ) 
			{
				$out[$name] = $atts[$name];
			}
			else 
			{
				$out[$name] = $default;
			}
		}
		return $out;
	}
}
?>