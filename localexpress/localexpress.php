<?php
/*
Plugin Name: Localexpress plugin
Plugin URI: http://github.com/Localexpress/localexpress-woocommerce
Description: A localexpress plugin for calculate and check if available
Version: 1.0.0
Author: Hans Broeke Boxture B.V.
Author URI: http://boxture.com
License: GPL2
*/

/**
 * Check if WooCommerce is active
 */
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

	function localexpress_shipping_method_init() {
		if ( ! class_exists( 'localexpress_shipping_method' ) ) {
			class localexpress_shipping_method extends WC_Shipping_Method {
				public function __construct() {
					$this->id                 = 'localexpress'; // Id for your shipping method. Should be uunique.
					$this->method_title       = __( 'Localexpress' );  // Title shown in admin
					$this->method_description = __( 'Localexpress method' ); // Description shown in admin

					$this->enabled            = "yes"; // This can be added as an setting but for this example its forced enabled
					$this->title              = "Localexpress"; // This can be added as an setting but for this example its forced.

					$this->init();
				}

				/**
				 * Init your settings
				 *
				 * @access public
				 * @return void
				 */
				function init() {
					// Load the settings API
					$this->init_form_fields(); // This is part of the settings API. Override the method to add your own settings
					$this->init_settings(); // This is part of the settings API. Loads settings you previously init.

					// Save settings in admin if you have any defined
					add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
				}

            function init_form_fields(){
               $this->form_fields = array(
                  'enabled' => array(
                      'title' => __( 'Enable/Disable', 'woocommerce' ),
                      'type' => 'checkbox',
                      'description' => __( 'Enable Localexpress Shipping.', 'woocommerce' )
                  ),
                  'APIkey' => array(
                      'title' => __( 'API key', 'woocommerce' ),
                      'type' => 'text',
                      'description' => __( 'This is the localexpress API key.', 'woocommerce' )
                  ),
                  'qa' => array(
                      'title' => __( 'QA', 'woocommerce' ),
                      'type' => 'checkbox',
                      'description' => __( 'This is a shop connected to the QA instead of live.', 'woocommerce' )
                  ),
                  'price' => array(
                      'title' => __( 'Price', 'woocommerce' ),
                      'type' => 'text',
                      'description' => __( 'If you want a default price and not use the calculated price.', 'woocommerce' )
                  ),
                  'height' => array(
                      'title' => __( 'Height', 'woocommerce' ),
                      'type' => 'text',
                      'description' => __( 'Default height of boxes.', 'woocommerce' )
                  ),
                  'length' => array(
                      'title' => __( 'Length', 'woocommerce' ),
                      'type' => 'text',
                      'description' => __( 'Default length of boxes.', 'woocommerce' )
                  ),
                  'width' => array(
                      'title' => __( 'Width', 'woocommerce' ),
                      'type' => 'text',
                      'description' => __( 'Default width of boxes.', 'woocommerce' )
                  ),
                  'name' => array(
                      'title' => __( 'Shop name', 'woocommerce' ),
                      'type' => 'text'
                  ),
                  'street' => array(
                      'title' => __( 'Shop street', 'woocommerce' ),
                      'type' => 'text'
                  ),
                  'housenr' => array(
                      'title' => __( 'Shop house number', 'woocommerce' ),
                      'type' => 'text'
                  ),
                  'zipcode' => array(
                      'title' => __( 'Shop zipcode', 'woocommerce' ),
                      'type' => 'text'
                  ),
                  'country' => array(
                      'title' => __( 'Shop country (iso 3166-2 eg NL)', 'woocommerce' ),
                      'type' => 'text'
                  ),
                  'email' => array(
                      'title' => __( 'Shop email address', 'woocommerce' ),
                      'type' => 'text'
                  ),
                  'phone' => array(
                      'title' => __( 'Shop phone number', 'woocommerce' ),
                      'type' => 'text'
                  )


              );
            }
            private function logStuff($log){
               if(is_array($log))
                  $log = json_encode($log);
               $fp = fopen('/tmp/woo.txt', 'a');
               fwrite($fp, $log."\n");
               fclose($fp);
            }
				/**
				 * calculate_shipping function.
				 *
				 * @access public
				 * @param mixed $package
				 * @return void
				 */
				public function calculate_shipping( $package ) {
               $to   = $package['destination'];
               $from = $this->settings;
               if(!empty($to['country']) && !empty($to['postcode']) && !empty($to['city']) && !empty($to['address']) && $to['country']=='NL' && $from['country'] == 'NL'){

                  $api_boxture   = $this->sentJSON("https://api.boxture.com/convert_address.php",json_encode(array("postal_code" => $to['postcode'],"address"=> $to['address'],"iso_country_code"=> $to['country'])));
                  $json_boxture  = json_decode($api_boxture['result'],true);
                  if(!empty($json_boxture['lat'])){
                     $return_av        = $this->sentBOXJSON("https://api".($this->settings['qa']=='yes' ? "-qa" : "-new").".boxture.com/available_features?latitude=".$json_boxture['lat']."&purpose=pickup&longitude=".$json_boxture['lon'],$this->settings['APIkey']);
                     $json_boxture_av  = json_decode($return['result'],true);
                     $longitude        = $json_boxture['lon'];
                     $latitude         = $json_boxture['lat']; 
                     $return           = (($return['info']['http_code']=='404' || $return['info']['http_code']=='422') ? false : true);
                  }
                  if($return){
                     $value   = 0;
                     $weight  = 0;
                     $orderLines = (array)$package["contents"];
                     foreach($orderLines as $ID => $orderLine){
                        $data = (array)$orderLine['data'];
                        if($data['virtual'] =='no'){
                           $value   += $orderLine["line_total"];
                           $weight  += $orderLine["weight"];
                        }
                     }
                     $json = array(
                        "service_type" => "",
                        "human_id" => null,
                        "state" => null,
                        "weight" => $weight,
                        "value" => $value,
                        "quantity" => 1,
                        "insurance" => false,
                        "dimensions" => array("width" => $this->settings['width'],"height" => $this->settings["height"],"length" => $this->settings['"length']),
                        "comments" => "",
                        "customer_email" => "",
                        "origin" =>  array(
                           "country" => $this->country[ucwords($from['country'])],
                           "formatted_address" => $from['street']." ".$from['housenr']."\n".$from['zipcode']." ".$from['city']."\n".$this->country[ucwords($from['country'])],
                           "iso_country_code" => ucwords($from['country']),
                           "locality" => $from['city'],
                           "postal_code" => $from['zipcode'],
                           "sub_thoroughfare" => $from['housenr'],
                           "thoroughfare" => $from['street'],
                           "contact" => "",
                           "email" => $from['email'],
                           "mobile" => $from['phone'],
                           "comments" => "",
                           "company" => $from['name'],
                        ),
                        "destination" => array(
                           "country" => $this->country[$to['country']],
                           "formatted_address" => $to['address'].(empty($to['address_2'])?"":$to['address_2']."\n")."\n".$to['postal_code']." ".$to['city']."\n".$this->country[$to['country']],
                           "iso_country_code" => $to['country'],
                           "locality" => $to['city'],
                           "postal_code" => $to['postcode'],
                           "sub_thoroughfare" => $json_boxture['subThoroughfare'],
                           "thoroughfare" => $json_boxture['thoroughfare'],
                           "contact" => $to['name'],
                           "email" => "noreply@boxture.com",
                           "mobile" => "",
                           "comments" => "",
                           "company" => $to['company_name']
                        ),
                        "waybill_nr" => null,
                        "vehicle_type" => "bicycle"
                     );
                     
                     $json = json_encode(array("shipment_quote" => $json));
      
                     $api_local_express_q    = $this->sentBOXJSON("https://api".($this->settings['qa']=='yes' ? "-qa" : "-new").".boxture.com/shipment_quotes",$this->settings['APIkey'],$json);
                     $json_local_express_q   = json_decode($api_local_express_q['result'],true);
                                    
                     if(empty($this->settings['price'])){
                        $price = $json_local_express_q['shipment_quote']['price'];
                     } else {
                        $price = $this->settings['price'];
                     }
                     
                     $rate = array(
      						'id' => $this->id,
      						'label' => $this->title,
      						'cost' => $price,
      						'calc_tax' => 'per_item'
      					);
      
      					// Register the rate
      					$this->add_rate( $rate );
      				}
               }
					
				}
				
				private function sentJSON($url,$post=false,$debug=false){
               $ch         = curl_init($url);     
               curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
               curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,false);
            
               if($post){
                  curl_setopt($ch, CURLOPT_POST, 1);
                  curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
               }
            
               $result = curl_exec($ch);
               $info = curl_getinfo($ch);
               curl_close($ch);
               return array("info" => $info,"result" => $result);
            }
            private function sentBOXJson($url,$key,$post=false){
               $ch         = curl_init($url);
               if($post){
                  curl_setopt($ch, CURLOPT_POST, 1);
                  curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
               }
               curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
               curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,false);
               curl_setopt($ch, CURLOPT_HTTPHEADER,array(
                      'Content-Type: application/json',
                      'Accept-Language: en',
                      'Connection: Keep-Alive',
                      'Authorization: Boxture '.$key));
               $result = curl_exec($ch);
               $info = curl_getinfo($ch);
               curl_close($ch);
               return array("info" => $info,"result" => $result);
            }
         
            function countries(){
               $this->_country = array(
               	'AF' => 'Afghanistan',
               	'AL' => 'Albania',
               	'DZ' => 'Algeria',
               	'AD' => 'Andorra',
               	'AO' => 'Angola',
               	'AI' => 'Anguilla',
               	'AG' => 'Antigua and Barbuda',
               	'AR' => 'Argentina',
               	'AM' => 'Armenia',
               	'AW' => 'Aruba',
               	'AU' => 'Australia',
               	'AT' => 'Austria',
               	'AZ' => 'Azerbaijan',
               	'BS' => 'Bahamas',
               	'BH' => 'Bahrain',
               	'BD' => 'Bangladesh',
               	'BB' => 'Barbados',
               	'BY' => 'Belarus',
               	'BE' => 'Belgium',
               	'BZ' => 'Belize',
               	'BJ' => 'Benin',
               	'BM' => 'Bermuda',
               	'BT' => 'Bhutan',
               	'BO' => 'Bolivia',
               	'BA' => 'Bosnia-Herzegovina',
               	'BW' => 'Botswana',
               	'BR' => 'Brazil',
               	'VG' => 'British Virgin Islands',
               	'BN' => 'Brunei Darussalam',
               	'BG' => 'Bulgaria',
               	'BF' => 'Burkina Faso',
               	'MM' => 'Burma',
               	'BI' => 'Burundi',
               	'KH' => 'Cambodia',
               	'CM' => 'Cameroon',
               	'CA' => 'Canada',
               	'CV' => 'Cape Verde',
               	'KY' => 'Cayman Islands',
               	'CF' => 'Central African Republic',
               	'TD' => 'Chad',
               	'CL' => 'Chile',
               	'CN' => 'China',
               	'CX' => 'Christmas Island (Australia)',
               	'CC' => 'Cocos Island (Australia)',
               	'CO' => 'Colombia',
               	'KM' => 'Comoros',
               	'CG' => 'Congo (Brazzaville),Republic of the',
               	'ZR' => 'Congo, Democratic Republic of the',
               	'CK' => 'Cook Islands (New Zealand)',
               	'CR' => 'Costa Rica',
               	'CI' => 'Cote d\'Ivoire (Ivory Coast)',
               	'HR' => 'Croatia',
               	'CU' => 'Cuba',
               	'CY' => 'Cyprus',
               	'CZ' => 'Czech Republic',
               	'DK' => 'Denmark',
               	'DJ' => 'Djibouti',
               	'DM' => 'Dominica',
               	'DO' => 'Dominican Republic',
               	'TP' => 'East Timor (Indonesia)',
               	'EC' => 'Ecuador',
               	'EG' => 'Egypt',
               	'SV' => 'El Salvador',
               	'GQ' => 'Equatorial Guinea',
               	'ER' => 'Eritrea',
               	'EE' => 'Estonia',
               	'ET' => 'Ethiopia',
               	'FK' => 'Falkland Islands',
               	'FO' => 'Faroe Islands',
               	'FJ' => 'Fiji',
               	'FI' => 'Finland',
               	'FR' => 'France',
               	'GF' => 'French Guiana',
               	'PF' => 'French Polynesia',
               	'GA' => 'Gabon',
               	'GM' => 'Gambia',
               	'GE' => 'Georgia, Republic of',
               	'DE' => 'Germany',
               	'GH' => 'Ghana',
               	'GI' => 'Gibraltar',
               	'GB' => 'Great Britain and Northern Ireland',
               	'GR' => 'Greece',
               	'GL' => 'Greenland',
               	'GD' => 'Grenada',
               	'GP' => 'Guadeloupe',
               	'GT' => 'Guatemala',
               	'GN' => 'Guinea',
               	'GW' => 'Guinea-Bissau',
               	'GY' => 'Guyana',
               	'HT' => 'Haiti',
               	'HN' => 'Honduras',
               	'HK' => 'Hong Kong',
               	'HU' => 'Hungary',
               	'IS' => 'Iceland',
               	'IN' => 'India',
               	'ID' => 'Indonesia',
               	'IR' => 'Iran',
               	'IQ' => 'Iraq',
               	'IE' => 'Ireland',
               	'IL' => 'Israel',
               	'IT' => 'Italy',
               	'JM' => 'Jamaica',
               	'JP' => 'Japan',
               	'JO' => 'Jordan',
               	'KZ' => 'Kazakhstan',
               	'KE' => 'Kenya',
               	'KI' => 'Kiribati',
               	'KW' => 'Kuwait',
               	'KG' => 'Kyrgyzstan',
               	'LA' => 'Laos',
               	'LV' => 'Latvia',
               	'LB' => 'Lebanon',
               	'LS' => 'Lesotho',
               	'LR' => 'Liberia',
               	'LY' => 'Libya',
               	'LI' => 'Liechtenstein',
               	'LT' => 'Lithuania',
               	'LU' => 'Luxembourg',
               	'MO' => 'Macao',
               	'MK' => 'Macedonia, Republic of',
               	'MG' => 'Madagascar',
               	'MW' => 'Malawi',
               	'MY' => 'Malaysia',
               	'MV' => 'Maldives',
               	'ML' => 'Mali',
               	'MT' => 'Malta',
               	'MQ' => 'Martinique',
               	'MR' => 'Mauritania',
               	'MU' => 'Mauritius',
               	'YT' => 'Mayotte (France)',
               	'MX' => 'Mexico',
               	'MD' => 'Moldova',
               	'MC' => 'Monaco (France)',
               	'MN' => 'Mongolia',
               	'MS' => 'Montserrat',
               	'MA' => 'Morocco',
               	'MZ' => 'Mozambique',
               	'NA' => 'Namibia',
               	'NR' => 'Nauru',
               	'NP' => 'Nepal',
               	'NL' => 'Netherlands',
               	'AN' => 'Netherlands Antilles',
               	'NC' => 'New Caledonia',
               	'NZ' => 'New Zealand',
               	'NI' => 'Nicaragua',
               	'NE' => 'Niger',
               	'NG' => 'Nigeria',
               	'KP' => 'North Korea (Korea, Democratic People\'s Republic of)',
               	'NO' => 'Norway',
               	'OM' => 'Oman',
               	'PK' => 'Pakistan',
               	'PA' => 'Panama',
               	'PG' => 'Papua New Guinea',
               	'PY' => 'Paraguay',
               	'PE' => 'Peru',
               	'PH' => 'Philippines',
               	'PN' => 'Pitcairn Island',
               	'PL' => 'Poland',
               	'PT' => 'Portugal',
               	'QA' => 'Qatar',
               	'RE' => 'Reunion',
               	'RO' => 'Romania',
               	'RU' => 'Russia',
               	'RW' => 'Rwanda',
               	'SH' => 'Saint Helena',
               	'KN' => 'Saint Kitts (St. Christopher and Nevis)',
               	'LC' => 'Saint Lucia',
               	'PM' => 'Saint Pierre and Miquelon',
               	'VC' => 'Saint Vincent and the Grenadines',
               	'SM' => 'San Marino',
               	'ST' => 'Sao Tome and Principe',
               	'SA' => 'Saudi Arabia',
               	'SN' => 'Senegal',
               	'YU' => 'Serbia-Montenegro',
               	'SC' => 'Seychelles',
               	'SL' => 'Sierra Leone',
               	'SG' => 'Singapore',
               	'SK' => 'Slovak Republic',
               	'SI' => 'Slovenia',
               	'SB' => 'Solomon Islands',
               	'SO' => 'Somalia',
               	'ZA' => 'South Africa',
               	'GS' => 'South Georgia (Falkland Islands)',
               	'KR' => 'South Korea (Korea, Republic of)',
               	'ES' => 'Spain',
               	'LK' => 'Sri Lanka',
               	'SD' => 'Sudan',
               	'SR' => 'Suriname',
               	'SZ' => 'Swaziland',
               	'SE' => 'Sweden',
               	'CH' => 'Switzerland',
               	'SY' => 'Syrian Arab Republic',
               	'TW' => 'Taiwan',
               	'TJ' => 'Tajikistan',
               	'TZ' => 'Tanzania',
               	'TH' => 'Thailand',
               	'TG' => 'Togo',
               	'TK' => 'Tokelau (Union) Group (Western Samoa)',
               	'TO' => 'Tonga',
               	'TT' => 'Trinidad and Tobago',
               	'TN' => 'Tunisia',
               	'TR' => 'Turkey',
               	'TM' => 'Turkmenistan',
               	'TC' => 'Turks and Caicos Islands',
               	'TV' => 'Tuvalu',
               	'UG' => 'Uganda',
               	'UA' => 'Ukraine',
               	'AE' => 'United Arab Emirates',
               	'UY' => 'Uruguay',
               	'UZ' => 'Uzbekistan',
               	'VU' => 'Vanuatu',
               	'VA' => 'Vatican City',
               	'VE' => 'Venezuela',
               	'VN' => 'Vietnam',
               	'WF' => 'Wallis and Futuna Islands',
               	'WS' => 'Western Samoa',
               	'YE' => 'Yemen',
               	'ZM' => 'Zambia',
               	'ZW' => 'Zimbabwe'
               );
               
            }
				
			}
		}
	}

	add_action( 'woocommerce_shipping_init', 'localexpress_shipping_method_init' );

	function add_your_shipping_method( $methods ) {
		$methods[] = 'localexpress_shipping_method';
		return $methods;
	}



	add_filter( 'woocommerce_shipping_methods', 'add_your_shipping_method' );
}