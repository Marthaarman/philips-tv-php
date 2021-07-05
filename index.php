<?php

include_once('philips_tv.php');

//Demo

//TV Connect settings
$protocol = 'https'; //use http or https
$ip = '192.168.1.184'; //ipv4 address of the TV
$port = '1926'; //port to connect to. (1925 for http, 1926 for https)
$apiv = '6'; //API Version. this is the Major given when you browse to $protocol://$ip:$port/system -> https://192.168.1.184:1926/system

//create TV class
$tv = new philips_tv($protocol, $ip, $port, $apiv);


if(isset($_GET['pair'])) {//index.php?pair
	echo "<h3>Start pairing</h3>";
	$tv->pair();
	echo "
		<form method='post' action='index.php?pair_confirm'>
			<input type='number' value='' placeholder='pin displayed on TV' name='pin' />
			<input type='submit' name='submit' value='continue' />
		</form>
	";
}elseif(isset($_GET['pair_confirm']) && isset($_POST['pin'])) {//index.php?pair_confirm
	echo "<h3>Confirm pairing</h3>";
	$tv->set_pin($_POST['pin']);
	$response = $tv->pair_confirm();
	if($response) {
		echo "<h4>Success!</h4>";
	}else {
		echo "<h4>Failed!</h4>";
	}
}elseif(isset($_GET['command'])) {//index.php?command=...
	$tv->command($_GET['command']);
}else {
	echo "
		<h3>Nothing to do.</h3>
		<a href='index.php?pair'>Pair</a><br />
		<a href='index.php?command=volume_up'>Volume up</a><br />
		<a href='index.php?command=volume_down'>Volume down</a>
	";
}


