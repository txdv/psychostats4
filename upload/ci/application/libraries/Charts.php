<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Charts {

	function Charts() {
		// NOP
	}

	/**
	 * Create a new base chart object.
	 * @param string $chart_type Type of chart to create.
	 * @param int $width Width of the chart.
	 * @param int $height Height of the chart.
	 *
	 */
	function create($chart_type ='column3d', $width = 320, $height = 240, $params = array()) {
		static $loaded = false;
		if (!$loaded) {
			// something is wrong with the FusionCharts_Gen.php file
			// and causes an empty output buffer to mess up my
			// ability to send headers. I can't figure it out, so
			// for now I do this.
			ob_start();
			require_once "charts/FusionCharts_Gen.php";
			ob_end_clean();
			$loaded = true;
		}

		$config =& get_config();

		$fc = new FusionCharts($chart_type, $width, $height);
		$fc->setSWFPath($config['charts_url']);
		
		// set some common defaults for all charts
		//$fc->setChartParams("animation=1;bgColor=C5CEDC;");
		$fc->setChartParams("animation=1;bgColor=FFFFFF;");
		
		// short-cut to allow caller to set some params via an array.
		if ($params and is_array($params)) {
			$str = '';
			reset($params);
			while (list($key, $val) = each($params)) {
				$str .= "$key=$val;";
			}
			// substr() removes trailing ';'
			$fc->setChartParams(substr($str, 0, -1));
		}
		return $fc;
	}

}

?>