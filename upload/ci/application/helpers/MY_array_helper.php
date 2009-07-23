<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 *	Extending the core ARRAY helper with more functions
 *
 */

/**
 * Push one array (or element) on to another after the specified key index.
 * @param array $ary Array to push onto.
 * @param string $key Key name to add $var after.
 * @param mixed $var The pushed value.
 */
function array_push_after(&$ary, $key, $var, $new_key = null) {
	$idx = array_key_index($ary, $key);
	if ($idx === false) {
		// $key did not exist so we push the value on the end of $ary
		if ($new_key) {
			$ary[$new_key] = $var;
		} else {
			$ary[$key] = $var;
		}
	} else {
		$new = array();
		foreach ($ary as $cur_key => $cur_var) {
			$new[$cur_key] = $cur_var;
			if ($cur_key === $key) {
				if ($new_key) {
					$new[$new_key] = $var;
				} else {
					$new[] = $var;
				}
			}
		}
		$ary = $new;
	}
}

/**
 * Push one array (or element) on to another before the specified key index.
 * @param array $ary Array to push onto.
 * @param string $key Key name to add $var before.
 * @param mixed $var The pushed value.
 */
function array_push_before(&$ary, $key, $var, $new_key = null) {
	$idx = array_key_index($ary, $key);
	if ($idx === false) {
		// $key did not exist so we push the value on the beginning
		$new = array();
		if ($new_key) {
			$new[$new_key] = $var;
		} else {
			$new[$key] = $var;
		}
		$ary = array_merge($new, $ary);
	} else {
		$new = array();
		foreach ($ary as $cur_key => $cur_var) {
			if ($cur_key === $key) {
				if ($new_key) {
					$new[$new_key] = $var;
				} else {
					$new[] = $var;
				}
			}
			$new[$cur_key] = $cur_var;
		}
		$ary = $new;
	}
}

/**
 * Returns the index of the key within the array.
 * @param array $ary Array to search.
 * @param mixed $search The key name to search for.
 * @return integer Returns an integer index.
 */
function array_key_index(&$ary, $search) {
	if (!is_array($ary)) {
		return false;
	}

	$idx = 0;
	foreach (array_keys($ary) as $key) {
		if ($key == $search) {
			return $idx;
		}
		++$idx;
	}
	
	return false;
}


?>