<?php
/**
 * @see ColorGradient
 */

/**
 * Create a colour gradient by defining colours at points allong
 * a line, then extrapolating intermediate ones.
 *
 * Change Log
 * =======================
 * v1.0 21-Feb-2007 (First release)
 *
 * Todo
 * =======================
 * + 	Colours are stored as hex in the class, it would be more 
 * 	 	efficient to store as rgb then convert to hex on output.
 * + 	Implement variouse types of output format. (eg: rgb, hex, 
 * 		named, etc...)
 * + 	Allow hex colours to be added without a hash character.
 * 
 * @author 		Hamish Morgan <hamish at kitty0 dot nospam org>
 * @copyright 	Copyright &copy; 2007, Hamish Morgan
 * @license 	http://www.gnu.org/licenses/gpl.txt GNU GPL v2
 * @version 	1.0 21-Feb-2007
 * @example 	../examples/gradient.php
 * @see			Gradient
 */
class ColourGradient extends Gradient {
 
	/**
	 * Hold an array of HTML colour names.
	 * 
	 * @var array
	 */
	private $_namedColors = null;
	
	/**
	 * @param array $array optional	Initial values
	 * @return ColourGradient
	 */
	public function __construct( $array = null) {
		$this->_createNamedColors();
		parent::__construct($array);
	}
	 
	/**
	 * @param int $offset
	 * @param mixed $val
	 * @return void
	 */
	public function offsetSet ($offset, $value) {
		if( is_array($value) )
			$value =  self::_Rgb2Hex($value);
		else {
			$value = strtolower($value);
			if (array_key_exists($value, $this->_namedColors)) {
				$value = '#' . $this->_namedColors[$value];
			} elseif (preg_match('/^[0-9a-f]{3,6}$/', $value)) {
				// force a hash mark if it looks HEX with no mark
				$value = '#' . $value;
			}
		}			
		parent::offsetSet($offset, $value);
	}
	
	/**
	 * Return the strongest contrasting colour to $hex
	 *
	 * @param string $hex
	 * @return string
	 */
	final public static function GetContrast($hex) {
		if(array_sum(self::_Hex2Rgb($hex)) / 3 <= 127)
			return '#ffffff';
		else return '#000000';
	}
	
	/**
	 * Given a position $n between $a and $b, this function returns
	 * a colour in a similar position between colours $c and $d.
	 *
	 * @param float $n
	 * @param float $a
	 * @param float $b
	 * @param string $c
	 * @param string $d
	 * @return string
	 */
	protected static function _Rescale($n, $a, $b, $c, $d) {
		$c = self::_Hex2Rgb($c);
		$d = self::_Hex2Rgb($d);

		return self::_Rgb2Hex(
		parent::_Rescale($n, $a, $b, $c[0], $d[0]),
		parent::_Rescale($n, $a, $b, $c[1], $d[1]),
		parent::_Rescale($n, $a, $b, $c[2], $d[2]));
	}

	/**
	 * Convert HTML hex colour code to an array containing
	 * rgb values.
	 *
	 * @param string hex
	 * @return array|null
	 */
	final private static function _Hex2Rgb($hex) {
		if(preg_match('/#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i', $hex, $m) == 1) {
			return array(hexdec($m[1]), hexdec($m[2]), hexdec($m[3]));
		} else {
			return null;
		}
	}

	/**
	 * Convert decimal rgb values to a HTML hex code.
	 *
	 * @param int $red
	 * @param int $green
	 * @param int $blue
	 * @return string|null
	 */
	final private static function _Rgb2Hex($r, $g=null, $b=null) {
		if(is_array($r)) {
			$b = $r[2];
			$g = $r[1];
			$r = $r[0];
		}
		return sprintf('#%06X', ($r<<16) + ($g<<8) + $b);

	}
	
	/**
	 * Create a static array of named HTML colours. Since $c is 
	 * static it the array is only stored once no matter how many
	 * instances of the class there are.
	 *
	 * @return void
	 */
	private function _createNamedColors() {
		if (!is_array($this->_namedColors)) {
			static $c = array('aliceblue'=>'F0F8FF','antiquewhite'=>'FAEBD7','aqua'=>'00FFFF','aquamarine'=>'7FFFD4','azure'=>'F0FFFF','beige'=>'F5F5DC','bisque'=>'FFE4C4','black'=>'000000',
			'blanchedalmond'=>'FFEBCD','blue'=>'0000FF','blueviolet'=>'8A2BE2','brown'=>'A52A2A','burlywood'=>'DEB887','cadetblue'=>'5F9EA0','chartreuse'=>'7FFF00','chocolate'=>'D2691E',
			'coral'=>'FF7F50','cornflowerblue'=>'6495ED','cornsilk'=>'FFF8DC','crimson'=>'DC143C','cyan'=>'00FFFF','darkblue'=>'00008B','darkcyan'=>'008B8B','darkgoldenrod'=>'B8860B',
			'darkgray'=>'A9A9A9','darkgreen'=>'006400','darkkhaki'=>'BDB76B','darkmagenta'=>'8B008B','darkolivegreen'=>'556B2F','darkorange'=>'FF8C00','darkorchid'=>'9932CC',
			'darkred'=>'8B0000','darksalmon'=>'E9967A','darkseagreen'=>'8FBC8F','darkslateblue'=>'483D8B','darkslategray'=>'2F4F4F','darkturquoise'=>'00CED1','darkviolet'=>'9400D3',
			'deeppink'=>'FF1493','deepskyblue'=>'00BFFF','dimgray'=>'696969','dodgerblue'=>'1E90FF','feldspar'=>'D19275','firebrick'=>'B22222','floralwhite'=>'FFFAF0',
			'forestgreen'=>'228B22','fuchsia'=>'FF00FF','gainsboro'=>'DCDCDC','ghostwhite'=>'F8F8FF','gold'=>'FFD700','goldenrod'=>'DAA520','gray'=>'808080','green'=>'008000',
			'greenyellow'=>'ADFF2F','honeydew'=>'F0FFF0','hotpink'=>'FF69B4','indianred'=>'CD5C5C','indigo'=>'4B0082','ivory'=>'FFFFF0','khaki'=>'F0E68C','lavender'=>'E6E6FA',
			'lavenderblush'=>'FFF0F5','lawngreen'=>'7CFC00','lemonchiffon'=>'FFFACD','lightblue'=>'ADD8E6','lightcoral'=>'F08080','lightcyan'=>'E0FFFF','lightgoldenrodyellow'=>'FAFAD2',
			'lightgrey'=>'D3D3D3','lightgreen'=>'90EE90','lightpink'=>'FFB6C1','lightsalmon'=>'FFA07A','lightseagreen'=>'20B2AA','lightskyblue'=>'87CEFA','lightslateblue'=>'8470FF',
			'lightslategray'=>'778899','lightsteelblue'=>'B0C4DE','lightyellow'=>'FFFFE0','lime'=>'00FF00','limegreen'=>'32CD32','linen'=>'FAF0E6','magenta'=>'FF00FF','maroon'=>'800000',
			'mediumaquamarine'=>'66CDAA','mediumblue'=>'0000CD','mediumorchid'=>'BA55D3','mediumpurple'=>'9370D8','mediumseagreen'=>'3CB371','mediumslateblue'=>'7B68EE',
			'mediumspringgreen'=>'00FA9A','mediumturquoise'=>'48D1CC','mediumvioletred'=>'C71585','midnightblue'=>'191970','mintcream'=>'F5FFFA','mistyrose'=>'FFE4E1','moccasin'=>'FFE4B5',
			'navajowhite'=>'FFDEAD','navy'=>'000080','oldlace'=>'FDF5E6','olive'=>'808000','olivedrab'=>'6B8E23','orange'=>'FFA500','orangered'=>'FF4500','orchid'=>'DA70D6',
			'palegoldenrod'=>'EEE8AA','palegreen'=>'98FB98','paleturquoise'=>'AFEEEE','palevioletred'=>'D87093','papayawhip'=>'FFEFD5','peachpuff'=>'FFDAB9','peru'=>'CD853F',
			'pink'=>'FFC0CB','plum'=>'DDA0DD','powderblue'=>'B0E0E6','purple'=>'800080','red'=>'FF0000','rosybrown'=>'BC8F8F','royalblue'=>'4169E1','saddlebrown'=>'8B4513','salmon'=>'FA8072',
			'sandybrown'=>'F4A460','seagreen'=>'2E8B57','seashell'=>'FFF5EE','sienna'=>'A0522D','silver'=>'C0C0C0','skyblue'=>'87CEEB','slateblue'=>'6A5ACD','slategray'=>'708090',
			'snow'=>'FFFAFA','springgreen'=>'00FF7F','steelblue'=>'4682B4','tan'=>'D2B48C','teal'=>'008080','thistle'=>'D8BFD8','tomato'=>'FF6347','turquoise'=>'40E0D0','violet'=>'EE82EE',
			'violetred'=>'D02090','wheat'=>'F5DEB3','white'=>'FFFFFF','whitesmoke'=>'F5F5F5','yellow'=>'FFFF00','yellowgreen'=>'9ACD32');
			$this->_namedColors = &$c;
		}
	}
	
} // end class ColourGradient


?>
