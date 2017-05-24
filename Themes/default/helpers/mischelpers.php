
<?php

function isSelected($current_val, $val) 
{
	
    return new \LightnCandy\SafeString($current_val == $val ? 'selected="selected' : '');
}


function get_text(...$key) {
	global $txt;
	if (is_array($key)) {
	    $key = implode($key);
	}
	return $txt[$key];
}

function textTemplate($template, ...$args) {
	return  new \LightnCandy\SafeString(sprintf($template, ...$args));
}


?>