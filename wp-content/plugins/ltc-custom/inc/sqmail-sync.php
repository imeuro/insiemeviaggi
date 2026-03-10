<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Woocommerce to Squalomail: clients list sync</title>
	<style type="text/css">
		html,body {
			margin: 0;
			padding: 0;
			font: 14px/22px Arial, Helvetica, sans-serif;
			color: #4d4d4d;
			background: #f5f5f5;
		}
		body {
			width: 100%;
			min-height: 100vh;
			max-width: 400px;
			margin: 0 auto;
			overflow-x: hidden;
			background: #fff;
			padding: 20px;
			box-sizing: border-box;
			line-break: anywhere;
		}
		#b64c {
			width: 100%;
			line-break: anywhere;
			overflow-y: scroll;
			height: 300px;
			display: inline-block;
			padding: 10px 15px 10px 5px;
			margin: 20px 0;
			box-sizing: border-box;
			border: 1px solid #999;
			border-radius: 3px;
			font-size: 11px;
			line-height: 14px;
			text-align: justify;
			background: #fff;
		}
	</style>
</head>
<body>

<?php 

if ( !defined('ABSPATH') )
    define('ABSPATH', '/var/www/vhosts/insiemeviaggi.com/httpdocs/');
    // define('ABSPATH', '/home/customer/www/stevenb138.sg-host.com/public_html/');
    
include './ltc-export-data.php';

$SQMkey = '01ngKDBQUQUnkcy6QITwW9Gyek7sZq9G';

function SQM_APIcall($url,$type,$params) {
	$curl = curl_init();
	curl_setopt_array($curl, array(
	  CURLOPT_URL => $url,
	  CURLOPT_RETURNTRANSFER => true,
	  CURLOPT_ENCODING => '',
	  CURLOPT_MAXREDIRS => 10,
	  CURLOPT_TIMEOUT => 0,
	  CURLOPT_FOLLOWLOCATION => true,
	  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	  CURLOPT_CUSTOMREQUEST => $type,
	  CURLOPT_POSTFIELDS => $params,
	  CURLOPT_HTTPHEADER => array(
	    'Content-Type: application/json'
	  ),
	));

	$response = curl_exec($curl);
	//print_r($response);
	return $response;
	curl_close($curl);
}




echo '[Sync db] starting sync...  <br>';
echo "[Sync db] service url: $api_url <br> <br>";


if ($_GET['mode'] && $_GET['mode'] === 'FULL') {

	// FULL IMPORT:

	echo "[FULL IMPORT] retrieving data from csv: $csv_filename <br>";

	$handle = fopen($csv_filename, "rb");
	$contents = stream_get_contents($handle);
	// print_r($contents);
	$b64_contents = base64_encode($contents);

	echo "[FULL IMPORT] encoding content: <br>";
	echo "<span id=\"b64c\">$b64_contents</span>";
	fclose($handle);
	echo "[FULL IMPORT] data encoded, calling API: <br>";

	// API CALL
	$curl = curl_init();
	curl_setopt_array($curl, array(
	  CURLOPT_URL => 'https://api.squalomail.com/v1/import-recipients-async',
	  CURLOPT_RETURNTRANSFER => true,
	  CURLOPT_ENCODING => '',
	  CURLOPT_MAXREDIRS => 10,
	  CURLOPT_TIMEOUT => 0,
	  CURLOPT_FOLLOWLOCATION => true,
	  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	  CURLOPT_CUSTOMREQUEST => 'POST',
	  CURLOPT_POSTFIELDS =>'{
	    "apiKey": "'.$SQMkey.'",
	    "autogenerateNames": "false",
	    "listIdsToAdd": ["1"],
	    "overwriteData": "1",
	    "clearPreviousListIds": "false",
	    "importAsDisabled": "false",
	    "base64EncodedFile": "'.$b64_contents.'"
	}',
	  CURLOPT_HTTPHEADER => array(
	    'Content-Type: application/json'
	  ),
	));

	$response = curl_exec($curl);

	curl_close($curl);
	echo "[FULL IMPORT] operation ended, response : <br>";

	//print_r($response);
	$jresponse = json_decode($response);
	echo " <br> <br><b>http status: $jresponse->httpStatusCode</b> <br> <br>";


} else {

	// SYNC ONLY

	// SI MA DOVEVO PRENDERE IL CSV MANNAGGIA

	echo "[SYNC ONLY] retrieving data from json: $json_filename <br>";

	$WP_contents = file_get_contents($json_filename);
	$WP_recipientList = json_decode($WP_contents);
	// echo "<br><pre>";
	// print_r($WP_recipientList);
	// echo "</pre><br>";

	$SQM_params = '{
		"apiKey": "'.$SQMkey.'",
		"listId": 1
	}';
	$SQM_contents = SQM_APIcall('https://api.squalomail.com/v1/get-subscribed-recipients','POST',$SQM_params);
	$SQM_contents = json_decode($SQM_contents);
	$SQM_recipientList = $SQM_contents->recipientList;

	// mi porto in root dell'array il wordpress_id che sta nei customAttributes di ogni recipient
	// ammazzatevi tutti, comunque...
	function searchForId($id, $obj) {
		foreach ($obj as $key => $val) {
			if ($val->name === $id) {
				return $key;
			}
		}
		return null;
	}
	foreach ($SQM_recipientList as $SQM_recipient) {
		$SQM_recipient_wordpress_id = searchForId('wordpress_id', $SQM_recipient->customAttributes);
		$SQM_recipient->WP_ID = $SQM_recipient->customAttributes[$SQM_recipient_wordpress_id]->value;
		// echo "<br><pre>";
		// print_r($SQM_recipient->WP_ID);
		// echo "</pre><br>";
	}
	
	// echo "<br><pre>";
	// print_r($SQM_recipientList);
	// echo "</pre><br>";

	// die();

	foreach ($WP_recipientList as $WPrecipient) {
		$WP_id = $WPrecipient->id;

		// CHECK IF USER WITH 'id_wordpress' ($WP_id) EXISTS and get its SQM_id
		$SQM_user = array_column($SQM_recipientList, null, 'WP_ID')[$WP_id] ?? false;

		if ($SQM_user !== false) {
			$SQM_id = $SQM_user->id;
			echo '<br>___________<br>$WPrecipient->id: '.$WP_id.' --->  '.$SQM_id;
			// UPDATE EXISTING USER
			// echo '<pre>';
			// print_r($SQM_user);
			// echo "</pre><br>";


			$UPD_params = '{
				"apiKey": "'.$SQMkey.'",
				"id":'.$SQM_id.',
				"accept":true,
				"confirmed":true,
				"customAttributes":[
					{
						"name":"codice_fiscale",
						"value":"'.$WPrecipient->billing->company.'"
					},
					{
						"name":"wordpress_id",
						"value":'.$WPrecipient->id.'
					},
					{
						"name":"indirizzo",
						"value":"'.$WPrecipient->billing->address_1.' '.$WPrecipient->billing->address_2.'"
					},
					{
						"name":"cap",
						"value":"'.$WPrecipient->billing->postcode.'"
					},
					{
						"name":"comune",
						"value":"'.$WPrecipient->billing->city.'"
					},
					{
						"name":"provincia",
						"value":"'.$WPrecipient->billing->state.'"
					},
					{
						"name":"stato",
						"value":"'.$WPrecipient->billing->country.'"
					}
				],
				"email":"'.$WPrecipient->email.'",
				"enabled":true,
				"html":true,
				"listIds":[1],
				"name":"'.$WPrecipient->first_name.'",
        "surname": "'.$WPrecipient->last_name.'",
        "phone": "'.$WPrecipient->billing->phone.'"
			}';

					// TODO:
					// more customAttributes...

					// {
					// 	"name":"codice_sconto",
					// 	"value":""
					// },
					// {
					// 	"name":"acquisti",
					// 	"value":''
					// },
					// {
					// 	"name":"note",
					// 	"value":""
					// },
					// {
					// 	"name":"azienda",
					// 	"value":""
					// },
					// {
					// 	"name":"regione",
					// 	"value":""
					// }

			print_r($UPD_params);
			SQM_APIcall('https://api.squalomail.com/v1/update-recipient','POST',$UPD_params);

		} else {
			echo '<br>___________<br>$WPrecipient->id: '.$WP_id.' --->  non existent!!';
			// INSERT AS NEW

			$ADD_params = '{
				"apiKey": "'.$SQMkey.'",
				"accept":true,
				"confirmed":true,
				"customAttributes":[
					{
						"name":"codice_fiscale",
						"value":"'.$WPrecipient->billing->company.'"
					},
					{
						"name":"wordpress_id",
						"value":'.$WPrecipient->id.'
					},
					{
						"name":"indirizzo",
						"value":"'.$WPrecipient->billing->address_1.' '.$WPrecipient->billing->address_2.'"
					},
					{
						"name":"cap",
						"value":"'.$WPrecipient->billing->postcode.'"
					},
					{
						"name":"comune",
						"value":"'.$WPrecipient->billing->city.'"
					},
					{
						"name":"provincia",
						"value":"'.$WPrecipient->billing->state.'"
					},
					{
						"name":"stato",
						"value":"'.$WPrecipient->billing->country.'"
					}
				],
				"email":"'.$WPrecipient->email.'",
				"enabled":true,
				"html":true,
				"listIds":[1],
				"name":"'.$WPrecipient->first_name.'",
        "surname": "'.$WPrecipient->last_name.'",
        "phone": "'.$WPrecipient->billing->phone.'",
        "gdprCanSend": true,
        "gdprCanTrack": true
			}';


			SQM_APIcall('https://api.squalomail.com/v1/create-recipient','POST',$ADD_params);




		}
		
		usleep(0.25);

	}



}


?>


</body>
</html>