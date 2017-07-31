<?php
/*
Plugin Name: Google Spreadsheet to Calendar
Plugin URI: https://github.com/atrus1701/google-spreadsheet-to-calendar
Description: Converts a google spreadsheet with dates and events to a table or agenda-style layout for Wordpress.
Version: 1.1.0
Author: Crystal Barton
Author URI: https://www.linkedin.edu/in/crystalbarton
GitHub Plugin URI: https://github.com/clas-web/google-spreadsheet-to-calendar
*/


add_filter( 'the_content', 'gstc_content' );
add_action( 'wp_head', 'gstc_build_stylesheet_url' );


/**
 * Imports a CSV file into an array.
 * @param  string  $file  The full path to the CSV file.
 * @param  string $delimiter  The file delimiter, usually comma (,).
 * @return  Array  Array form of CSV file.
 */
if( !function_exists('gstc_csv_to_array') ):
function gstc_csv_to_array( $file, $delimiter )
{
	if (($handle = fopen($file, 'r')) !== FALSE)
	{ 
    	$i = 0; 
    	while (($lineArray = fgetcsv($handle, 4000, $delimiter, '"')) !== FALSE)
    	{ 
      		for ($j = 0; $j < count($lineArray); $j++)
      		{ 
        		$arr[$i][$j] = $lineArray[$j]; 
      		} 
      	$i++; 
    	} 
    	fclose($handle); 
  	} 
  	return $arr; 
}
endif;


/**
 * Get the table html for the an event's layout.
 * @param  Array  $event  The event information.
 * @return  string  The html.
 */
if( !function_exists('gstc_get_table_layout_for_event') ):
function gstc_get_table_layout_for_event( $event )
{
	$layout = '';
	
	for( $c = 1; $c < count($event); $c++ )
	{
		if( ($event[$c] == 'null') || (trim($event[$c]) == '') ) {
			$layout .= '<td></td>';
			continue;
		}
		
		$layout .= '<td>'.$event[$c].'</td>';
	}
	
	return $layout;
}
endif;


/**
 * Get the div html for the an event's layout.
 * @param  Array  $event  The event information.
 * @param  Array  $headers  The associated label names for each event item.
 * @return  string  The html.
 */
if( !function_exists('gstc_get_div_layout_for_event') ):
function gstc_get_div_layout_for_event( $event, $headers )
{
	$layout = '';
	
	for( $c = 1; $c < count($event); $c++ )
	{
		if( ($event[$c] == 'null') || (trim($event[$c]) == '') ) {
			continue;
		}
		
		$layout .= '<div class="column'.$c.'">';
					
		if( (count($headers) > $c) && (trim($headers[$c]) !== '') && ($c > 1) )
			$layout .= '<label>'.$headers[$c].':</label> ';
		
		$layout .= $event[$c] . '</div>';
	}

	return $layout;
}
endif;


/**
 * Parse the content for the Spreadsheet to Calendar shortcode.
 * @param  string  $content  The content.
 * @return  string  The content with converted shortcode.
 */
if( !function_exists('gstc_content') ):
function gstc_content( $content )
{
	$matches = NULL;
	$num_matches = preg_match_all("/\[spreadsheet-to-calendar(.+)\]/", $content, $matches, PREG_SET_ORDER);

	if( ($num_matches !== FALSE) && ($num_matches > 0) )
	{
		for( $i = 0; $i < $num_matches; $i++ )
		{
			$key = NULL;
			$format = 'm/d/Y';
			$class = 'gstc-sheet';
			$type = 'table';
			$gid = NULL;
		
			$m = NULL;
			
			if( preg_match("/key=\"([^\"]+)\"/", $matches[$i][0], $m) )
				$key = $m[1];
		
			if( preg_match("/format=\"([^\"]+)\"/", $matches[$i][0], $m) )
				$format = $m[1];
		
			if( preg_match("/class=\"([^\"]+)\"/", $matches[$i][0], $m) )
				$class .= ' '.$m[1];

			if( preg_match("/type=\"([^\"]+)\"/", $matches[$i][0], $m) )
				$type = $m[1];

			if( preg_match("/gid=\"([^\"]+)\"/", $matches[$i][0], $m) )
				$gid = $m[1];

			$class = trim($class);
			
			$dates = array();
			//$feed = 'https://docs.google.com/spreadsheet/pub?key=0AmAQIHxHzv4ydEhtNWhqci1iOWJGMXQ4UlVpVWVkVmc&single=true&gid=1&output=csv';
			//$feed = 'https://spreadsheets.google.com/tq?&tq=select%20*&key=0AmAQIHxHzv4ydEhtNWhqci1iOWJGMXQ4UlVpVWVkVmc&gid=1&tqx=out:csv';
			
			if( gid !== NULL )
				$key .= '&gid='.$gid;
			
			$feed = 'https://spreadsheets.google.com/tq?&tq=select%20*&key='.$key.'&tqx=out:csv';
			$info = gstc_csv_to_array($feed, ',');
			$headers = array_shift($info);
			
			foreach( $info as $data )
			{
				$d = date_parse_from_format($format, $data[0]);
				if( count($d['errors']) > 0 )
					continue;
				
				$year = $d['year'];
				$month = $d['month'];
				$day = $d['day'];
				
				if( !array_key_exists($year, $dates) )
					$dates[$year] = array();
				
				if( !array_key_exists($month, $dates[$year]) )
					$dates[$year][$month] = array();
				
				if( !array_key_exists($day, $dates[$year][$month]) )
					$dates[$year][$month][$day] = array();
				
				switch($type)
				{
					case('div'):
						$dates[$year][$month][$day][] = gstc_get_div_layout_for_event($data, $headers);
						break;
					case('table'):
					default:
						$dates[$year][$month][$day][] = gstc_get_table_layout_for_event($data);
						break;
				}
			}
			
			ksort( $dates );
			$years = array_keys( $dates );
			for( $y = 0; $y < count($years); $y++ )
			{
				$year = $years[$y];
				ksort( $dates[$year] );
				$months = array_keys( $dates[$year] );
				for( $m = 0; $m < count($months); $m++ )
				{
					$month = $months[$m];
					ksort( $dates[$year][$month] );
				}
			}
			
			switch($type)
			{
				case('div'):
					$layout = '<div class="'.$class.'">';
					break;
				case('table'):
				default:
					$layout = '<table class="'.$class.'"><thead><tr>';
					for( $h = 0; $h < count($headers); $h++ )
						$layout .= '<td>'.$headers[$h].'</td>';
					$layout .= '</tr></thead><tbody>';
					break;
			}

			ksort( $dates );
			$years = array_keys( $dates );

			for( $y = 0; $y < count($years); $y++ )
			{
				$year = $years[$y];
				ksort( $dates[$year] );
				$months = array_keys( $dates[$year] );

				for( $m = 0; $m < count($months); $m++ )
				{
					$month = $months[$m];
					ksort( $dates[$year][$month] );
					
					switch( $month )
					{
						case(1):  $month_name = 'January';   break;
						case(2):  $month_name = 'February';  break;
						case(3):  $month_name = 'March';     break;
						case(4):  $month_name = 'April';     break;
						case(5):  $month_name = 'May';       break;
						case(6):  $month_name = 'June';      break;
						case(7):  $month_name = 'July';      break;
						case(8):  $month_name = 'August';    break;
						case(9):  $month_name = 'September'; break;
						case(10): $month_name = 'October';   break;
						case(11): $month_name = 'November';  break;
						case(12): $month_name = 'December';  break;
						default:  $month_name = '';          break;
					}
					
					$days = array_keys( $dates[$year][$month] );
					for( $d = 0; $d < count($days); $d++ )
					{
						$day = $days[$d];
						
						switch($type)
						{
							case('div'):
								$layout .= '<div class="gstc-day">';
								$layout .= '<div class="gstc-date"><span class="gstc-date-month">'.$month_name.'</span> <span class="gstc-date-day">'.$day.'</span></div><div class="gstc-events">';
								break;
							case('table'):
							default:
								$layout .= '<tr><td class="gstc-day" rowspan="'.count($dates[$year][$month][$day]).'">'.$month_name.' '.$day.'</td>';
								break;
						}
			
						for( $e = 0; $e < count($dates[$year][$month][$day]); $e++ )
						{
							$event = $dates[$year][$month][$day][$e];
							
							switch($type)
							{
								case('div'):
									$layout .= '<div class="gstc-event">'.$event.'</div>';
									break;
								case('table'):
								default:
									if( $e > 0 ) $layout .= '<tr>';
									$layout .= $event.'</tr>';
									break;
							}
						}
						
						switch($type)
						{
							case('div'):
								$layout .= '</div></div>';
								break;
							case('table'):
							default:
								break;
						}
					}
				}
			}

			switch($type)
			{
				case('div'):
					$layout .= '</div>';
					break;
				case('table'):
				default:
					$layout .= '</tbody></table>';
					break;
			}
			
			$content = str_replace($matches[$i][0], $layout, $content);
		}
	}
		
	return $content;
}
endif;


/**
 * Print the stylesheet link in the wp head.
 */
if( !function_exists('gstc_build_stylesheet_url') ):
function gstc_build_stylesheet_url() {
    echo '<link rel="stylesheet" href="' . plugin_dir_url( __FILE__ ) . 'styles.css" />';
}
endif;

