<?php
/**
 * @see Gradient
 */

/**
 * Create a gradient of numeric values by defining keys points along
 * a line, then extrapolating intermediate ones. This class can be
 * extended to support other data type by overriding to Rescale()
 * method (see ColourGradient).
 * 
 * Requires PHP version 5 or later. Tested on Apache/1.3.33 (Darwin) 
 * PHP/5.1.6 and Apache/2.0.54 (Ubuntu) PHP/5.0.5-2ubuntu1.6.
 * 
 * Change Log
 * =======================
 * v1.0 21-Feb-2007 (First version.)
 * 
 * Todo
 * ======================
 * + Possibly get it working as a multidimensional array for
 * 	 illustrator style gradient mesh.
 * + Variations so output gradients could be inexact for effect.
 * + It may be possible to update caced data rather than flush it.
 * + May be cleaner if it implements FilterIterator.
 * 
 * @author 		Hamish Morgan <hamish at kitty0 dot nospam org>
 * @copyright 	Copyright &copy; 2007, Hamish Morgan
 * @license 	http://www.gnu.org/licenses/gpl.txt GNU GPL v2
 * @version 	1.0 21-Feb-2007
 * @example 	../examples/gradient.php
 * @see 		ColourGradient
 */
class Gradient implements ArrayAccess, SeekableIterator {
	
	/**
	 * Store all the defined points on the gradient
	 * 
	 * @var array	
	 */
	private $_items = array();
	
	/**
	 * Reference index of item positions.
	 * 
	 * @var array	
	 */
	private $_posIndex = array();
	
	/**
	 * Index of current write postion in list.
	 * 
	 * @var int		
	 */
	private $_iSet = 0;
	
	/**
	 * Index of current read position in list. 
	 * 
	 * @var int		
	 */
	private $_iGet = 0;
	
	/**
	 * Number of points added to gradient.
	 * 
	 * @var int		
	 */
	private $_count = 0;
	
	
	/**
	 * Reference array of points ordered by their position.
	 * 
	 * @var array|null
	 */
	private $_orderedItems;
	
	/**
	 * Stores cached return values so they don't have to be 
	 * recalculated every time.
	 * 
	 * @var array
	 */
	private $_getCache;
	
	/**
	 * var array[2]
	 */
	private $_range = array(0, 100);
	
	/**
	 * @param array $array optional	Initial values
	 * @return Gradient
	 */
	public function __construct( $array = null) {
		if($array !== null) {
			foreach($array as $k => $v)
				$this[$k] = $v;
		}
		$this->_Flush();
	}

	/**
	 * @param float min
	 * @param float max
	 * @return void
	 */
	public function SetRange($min, $max) {
		foreach($this->_items as $k => $v) {
			unset($this->_posIndex[$v['pos']]);
			$this->_items[$k]['pos'] = self::_Rescale($v['pos'],
				$this->_range[0], $this->_range[1], $min, $max);
			$this->_posIndex[$this->_items[$k]['pos']] = $k;
		}
		$this->_range = array($min, $max);
	}
	
	/**
	 * @return void;
	 */
	public function rewind() {
		$this->_iGet = 0;
	}
	
	/**
	 * @return mixed|false
	 */
	public function current() {
		if($this->offsetExists($this->_iGet))
			return $this->offsetGet($this->_iGet);	
		else return false;
	}
	
	/**
	 * return int
	 */
	public function key() {
		return $this->_iGet;
	}
	
	/**
	 * @return mixed|false
	 */
	public function next() {
		$this->_iGet ++;
		return $this->current();
	}
	
	/**
	 * Whether current get offset is valid or not.
	 * 
	 * @return bool		True if valid, false otherwise.
	 */
	public function valid() {
	    return $this->offsetExists($this->_iGet);
	}	
	
	/**
	 * @param int $index
	 * @return void
	 */
	public function seek($index) {
		
		$index = self::_Rescale($index, 
			$this->_range[0], $this->_range[1], 0, 100);
			
		$this->_iGet = $index;
 	    if (!$this->valid())
            throw new OutOfBoundsException('Invalid seek position');
	}
	
	/**
	 * See if an offset is set. Actually since this class will
	 * will return averaged index values it will always return
	 * true if the offset it with range and at least 1 item has
	 * been added.
	 * 
	 * @param int $offset	Index to check.
	 * @return bool			True if set, false otherwise.
	 */
	public function offsetExists ($offset) {
		return $this->_count > 0 
			&& $offset >= $this->_range[0] 
			&& $offset <= $this->_range[1];
	}
 	
	/**
	 * Retrieve a value at index $offset. Since this class will
	 * return averaged values between set points it is possible
	 * to retrieve any valid offset, even a float.
	 * 
	 * @param float $offset	Index of value to retrieve
	 * @return mixed		Value that was found
	 */
	public function offsetGet ($offset) {
		$val = null;

		//$offset = self::_Rescale($offset, 
		//	$this->_range[0], $this->_range[1], 0, 100);
		
		// Check $offset hasn't been set exactly.
		if(isset($this->_posIndex[$offset]))
			$val = $this->_items[$this->_posIndex[$offset]]['val'];
		
		// Check if $offset has been cached. (convert to string for
		// offset hash so floats work.)
		elseif(isset($this->_getCache["$offset"])) 
			$val = $this->_getCache["$offset"];
		
		else {
			$this->_Order();
			$first = $this->_orderedItems[0];
			$last = end($this->_orderedItems);
		
			// Anything below the lowest value is the same
			if($offset <= $first['pos'])
				$val = $first['val'];
			// Anything above the highest value is the same
			elseif($offset >= $last['pos'])
				$val = $last['val'];
			else 
				// Find between which points the offset lies.
				for($i = 1, $prev = $first; 
					$i < $this->_count && $val === null;
					$i++, $prev = $next) 
				{
					$next = $this->_orderedItems[$i];
					if($offset >= $prev['pos'] && $offset <= $next['pos'] )
						$val = $this->_Rescale($offset, $prev['pos'], 
							$next['pos'], $prev['val'], $next['val']);
				}
			// Update cache.
			$this->_getCache["$offset"] = $val;
		}
		return $val;	
 	}
 	
 	/**
 	 * Add an item $value at index $offset, update if already set.
 	 * 
 	 * @param int $offset	Index of item
 	 * @param mixed $val	The item to add.
 	 * @return void
 	 * @throws OutOfBoundsException
 	 */
 	public function offsetSet ($offset, $value) {
 		
		//$offset = self::_Rescale($offset, 
		//	$this->_range[0], $this->_range[1], 0, 100);
				
 		if($offset < 0 || $offset > 100)
 			throw new OutOfBoundsException("Offset $offset is 
			outside of valid range: 0-100.");
 		
 		if(isset($this->_posIndex[$offset])) { //update
			$id = $this->_posIndex[$offset];
			$this->_items[$id] = array(
				'val' => $value,
				'pos' => $offset
			); 
		} else { // add
			$this->_Flush();
			$this->_items[$this->_iSet] = array(
				'val' => $value,
				'pos' => $offset
			);
			$this->_posIndex[$offset] = $this->_iSet;	
			$this->_iSet++;
			$this->_count++;
		}
 	}
 	
 	/**
 	 * Remove an item at index $offset if it exists.
 	 * 
 	 * @param int $offset	Index it item to remove.
 	 * @return void
 	 */
 	public function offsetUnset ($offset) {
 		//$offset = self::_Rescale($offset, 
		//	$this->_range[0], $this->_range[1], 0, 100);
 		
		if(isset($this->_posIndex[$pos])) {
			$this->_Flush();
			unset($this->_items[$this->_posIndex[$pos]]);
			unset($this->_posIndex[$pos]);
			$this->_count--;
		}
 	}
 	
 	/**
 	 * Returns the number of items added
 	 * 
 	 * @return int The number of items added.
 	 */
 	public function count() {
 		return $this->_count;
 	}

 	/**
	 * Empty the cached data.
	 * 
	 * @return void
	 */
	private function _Flush() {
		$this->_orderedItems = null;
		$this->_getCache = array();
	}
	
	
	/**
	 * Create and ordered array of references to point items. Sorted
	 * by pos.
	 * 
	 * @return void
	 */
	private function _Order() {
		if($this->_orderedItems === null) {

			$comparator = create_function('$a, $b', 
				'return $a["pos"] - $b["pos"];');
			$this->_orderedItems = array();
			foreach($this->_items as $k => $v)
				$this->_orderedItems[] = &$this->_items[$k];
				
			usort($this->_orderedItems, $comparator);
		}
	}

	/**
	 * Given a position $n between $a and $b, this function returns 
	 * a value in a similar position between $c and $d. 
	 * 
	 * @param float $n	Number of rescale
	 * @param float $a	From scale minimum
	 * @param float $b	From scale max
	 * @param float $c	To scale min
	 * @param float $d	To scale max
	 * @return float	Number rescaled
	 */
	protected static function _Rescale($n, $a, $b, $c, $d) {
		return ($n - $a) / ($b - $a) * ($d - $c) + $c;	
	}
	

} // end class Gradient


?>