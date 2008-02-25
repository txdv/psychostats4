<?php

if (defined("CLASS_XMLDATA_PHP")) return 1; 
define("CLASS_XMLDATA_PHP", 1); 

###################################################################################
#
# XML Library, by Keith Devens, version 1.2b
# http://keithdevens.com/software/phpxml
#
# This code is Open Source, released under terms similar to the Artistic License.
# Read the license at http://keithdevens.com/software/license
#
# Slight modifications by Jason Morriss <stormtrooper@psychostats.com>
# Most notibly: changed how attributes are returned and handled.
#
###################################################################################

###################################################################################
# XML_unserialize: takes raw XML as a parameter (a string)
# and returns an equivalent PHP data structure
###################################################################################
function XML_unserialize(&$xml){
	$xml_parser = &new XMLstruct();
	$data = &$xml_parser->parse($xml);
	$xml_parser->destruct();
	return $data;
}
###################################################################################
# XML_serialize: serializes any PHP data structure into XML
# Takes one parameter: the data to serialize. Must be an array.
###################################################################################
function XML_serialize(&$data, $root_key = 'data', $level = 0, $prior_key = NULL) {
	if ($level == 0) { 
		ob_start(); 
		echo '<?xml version="1.0" ?>',"\n<$root_key>\n"; 
	}
	while(list($key, $value) = each($data))
		if (strpos($key, '@') === false) # not an attribute
			# we don't treat attributes by themselves, so for an empty element
			# that has attributes you still need to set the element to NULL

			if (is_array($value) and array_key_exists(0, $value)) {
				XML_serialize($value, $root_key, $level+1, $key);
			} else {
				$tag = $prior_key ? $prior_key : $key;
				if (is_numeric($tag)) $tag = "key_$tag";
				echo str_repeat("\t", $level+1),'<',$tag;
				if (array_key_exists("@$key", $data)) { # if there's an attribute for this element
					while (list($attr_name, $attr_value) = each($data["@$key"])) {
						echo ' ',$attr_name,'="',htmlspecialchars($attr_value),'"';
					}
					reset($data["@$key"]);
				}

				if (is_null($value)) echo " />\n";
				elseif (!is_array($value)) echo '>',htmlspecialchars($value),"</$tag>\n";
				else echo ">\n",XML_serialize($value, $root_key, $level+1),str_repeat("\t", $level+1),"</$tag>\n";
			}
	reset($data);
	if ($level == 0){ 
		$str = ob_get_contents(); 
		ob_end_clean(); 
		return "$str</$root_key>\n"; 
	}
}
###################################################################################
# XML class: utility class to be used with PHP's XML handling functions
###################################################################################
class XMLstruct {
	var $parser;   #a reference to the XML parser
	var $document; #the entire XML structure built up so far
	var $parent;   #a pointer to the current parent - the parent will be an array
	var $stack;    #a stack of the most recent parent at each nesting level
	var $last_opened_tag; #keeps track of the last tag opened.

	function XMLstruct(){
 		$this->parser = &xml_parser_create();
		xml_parser_set_option(&$this->parser, XML_OPTION_CASE_FOLDING, false);
		xml_set_object(&$this->parser, &$this);
		xml_set_element_handler(&$this->parser, 'open','close');
		xml_set_character_data_handler(&$this->parser, 'data');
	}
	function destruct(){ xml_parser_free(&$this->parser); }
	function & parse(&$data){
		$this->document = array();
		$this->stack    = array();
		$this->parent   = &$this->document;
		return xml_parse(&$this->parser, &$data, true) ? $this->document : NULL;
	}
	function open(&$parser, $tag, $attributes){
		$this->data = ''; #stores temporary cdata
		$this->last_opened_tag = $tag;
		if(is_array($this->parent) and array_key_exists($tag,$this->parent)){ #if you've seen this tag before
			if(is_array($this->parent[$tag]) and array_key_exists(0,$this->parent[$tag])){ #if the keys are numeric
				#this is the third or later instance of $tag we've come across
				$key = count_numeric_items($this->parent[$tag]);
			}else{
				#this is the second instance of $tag that we've seen. shift around
				if(array_key_exists("@$tag",$this->parent)){
					$arr = array('@0'=> &$this->parent["@$tag"], &$this->parent[$tag]);
					unset($this->parent["@$tag"]);
				}else{
					$arr = array(&$this->parent[$tag]);
				}
				$this->parent[$tag] = &$arr;
				$key = 1;
			}
			$this->parent = &$this->parent[$tag];
		}else{
			$key = $tag;
		}
		if($attributes) $this->parent["@$key"] = $attributes;
		$this->parent  = &$this->parent[$key];
		$this->stack[] = &$this->parent;
	}
	function data(&$parser, $data){
		if($this->last_opened_tag != NULL) #you don't need to store whitespace in between tags
			$this->data .= $data;
	}
	function close(&$parser, $tag){
		if($this->last_opened_tag == $tag){
			$this->parent = $this->data;
			$this->last_opened_tag = NULL;
		}
		array_pop($this->stack);
		if($this->stack) $this->parent = &$this->stack[count($this->stack)-1];
	}
}
function count_numeric_items(&$array){
	return is_array($array) ? count(array_filter(array_keys($array), 'is_numeric')) : 0;
}

?>
