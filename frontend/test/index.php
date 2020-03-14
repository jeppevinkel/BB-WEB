<?php

$str = '<b>[<color=#00ff00ff>EU-West</color>] <color=#000080ff>JopoCraft</color> </b> | <color=#00ff00ff>FF = ON</color> | <color=#ff0000ff>Experimental</color> <color=#00000000><size=1>SM119.1.4.5 (EXILED)</size></color>';

$newstr = preg_replace('/<color=(.*?)>(.*?)<\/color>/','<span style="color:$1;">$2</span>',$str);

echo ConvertUnityText($str);

function ConvertUnityText($str){
	$newstr = preg_replace('/<color=(.*?)>(.*?)<\/color>/','<span style="color:$1;">$2</span>',$str);
	$newstr = preg_replace('/<size=(.*?)>(.*?)<\/size>/','<span id="unity-size" style="font-size:$1px;">$2</span>',$newstr);

	return $newstr;
}


//<span id="unity-size" style="font-size:1px;">SM119.1.4.5 (EXILED)</span>

?>