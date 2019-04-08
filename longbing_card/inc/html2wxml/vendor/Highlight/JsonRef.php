<?php  namespace Highlight;
class JsonRef 
{
	private $paths = NULL;
	private function getPaths(&$s, $r = "#") 
	{
		$this->paths[$r] =& $s;
		if( is_array($s) || is_object($s) ) 
		{
			foreach( $s as $k => &$v ) 
			{
				if( $k !== "\$ref" ) 
				{
					$this->getPaths($v, ($r == "#" ? "#" . $k : (string) $r . "." . $k));
				}
			}
		}
	}
	private function resolvePathReferences(&$s) 
	{
		if( is_array($s) || is_object($s) ) 
		{
			foreach( $s as $k => &$v ) 
			{
				if( $k === "\$ref" ) 
				{
					$s = $this->paths[$v];
				}
				else 
				{
					$this->resolvePathReferences($v);
				}
			}
		}
	}
	public function decode($json) 
	{
		$this->paths = array( );
		$x = (is_string($json) ? json_decode($json) : $json);
		$this->getPaths($x);
		$this->resolvePathReferences($x);
		return $x;
	}
}
?>