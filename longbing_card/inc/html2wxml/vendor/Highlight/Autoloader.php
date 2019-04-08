<?php  namespace Highlight;
class Autoloader 
{
	public static function load($class) 
	{
		if( substr($class, 0, 10) !== "Highlight\\" ) 
		{
			return false;
		}
		$c = str_replace("\\", "/", substr($class, 10)) . ".php";
		$res = include(__DIR__ . "/" . $c);
		return ($res == 1 ? true : false);
	}
}
?>