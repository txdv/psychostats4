<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

require 'color/gradient.class.php';
require 'color/colourgradient.class.php';

class Color extends ColourGradient {
	 //nothing to do here except wrap the parent object in our library
	 //class.

	// helper method to return a new colorgradient object
	public function create($ary = null) {
		$c = new Color($ary);
		return $c;
	}
}

?>