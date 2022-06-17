<!DOCTYPE html>
<html>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<style>
	*{
		margin: 0;
		padding: 0;
	}
	body {
		width: 100%;
		overflow: hidden;
		background-color: black;
		color: white;
	}
	canvas {
		width: 100%;
		height: 100%;
	}
	#loading {
		position:absolute;
		top:0;
		left:0;
	}
</style>
<title>Canvas Player</title>
<body>
<?php

	// Code highlighter: https://pinetools.com/syntax-highlighter, theme: XML, style: Tomorrow night blue
	// 
	// TO-DO:
	// - formula for frame based processing to listen for the FPS command
	// - test sound support

	function is_json( $value ) {
		if ( is_numeric( $value ) ) { return true; }
		if ( ! is_string( $value ) ) { return false; }
		if ( strlen( $value ) < 2 ) { return false; }
		if ( 'null' === $value ) { return true; }
		if ( 'true' === $value ) { return true; }
		if ( 'false' === $value ) { return false; }
		if ( '{' != $value[0] && '[' != $value[0] && '"' != $value[0] ) { return false; }
		$last_char = $value[strlen($value) -1];
		if ( '{' == $value[0] && '}' != $last_char ) { return false; }
		if ( '[' == $value[0] && ']' != $last_char ) { return false; }
		if ( '"' == $value[0] && '"' != $last_char ) { return false; }
		return null !== json_decode( $value );
	}

	// validate and ignore invalid video endpoints
	$chunks = array();
	foreach ($_POST['chunks'] as $chunk) {
		$headers = @get_headers($chunk, true);
		if (@strpos($headers['content-type'], 'video') === 0) {
			$chunks[] = $chunk;
		}else{ 
			// invalid video url
			echo "<script>console.log('Invalid video URL: $chunk');</script>";
		}
	}
	$chunks = json_encode($chunks);

?>
<canvas id="canvas"></canvas>
<canvas id="loading" width="32" height="32">
<script>
	// are we on iOS
	iOSDevice = !!navigator.platform.match(/iPhone|iPod|iPad/);

	// gradient animation while loading
	if (!iOSDevice){
		var c = document.getElementById("loading");
		var c2d = c.getContext('2d');
		var col = function(x, y, r, g, b) {	c2d.fillStyle = "rgb(" + r + "," + g + "," + b + ")";	c2d.fillRect(x, y, 1,1); }
		var R = function(x, y, t) {	return( Math.floor(192 + 64*Math.cos( (x*x-y*y)/300 + t )) ); }
		var G = function(x, y, t) {	return( Math.floor(192 + 64*Math.sin( (x*x*Math.cos(t/4)+y*y*Math.sin(t/3))/300 ) ) ); }
		var B = function(x, y, t) {	return( Math.floor(192 + 64*Math.sin( 5*Math.sin(t/9) + ((x-100)*(x-100)+(y-100)*(y-100))/1100) )); }
		var t = 0;
		var run = function() {
			for(x=0;x<=35;x++) { for(y=0;y<=35;y++) { col(x, y, R(x,y,t), G(x,y,t), B(x,y,t)); } }
			t = t + 0.120;
			if (videoCycleComplete) return false;
			window.requestAnimationFrame(run);
		}
		run();
	}

	// do some canvas player preps
	var canvas = document.getElementById("canvas");
	canvas.width = window.innerWidth;
	canvas.height = window.innerHeight;
	var ctx = canvas.getContext("2d");

	// load video chunk variables from PHP > JSON object array
	var data = <?php echo json_encode($chunks); ?>;
	var chunks = new Array();
	chunks = JSON.parse(data);
	console.log(chunks);

	// load all video chunks
	var videoContainer = new Array();
	var video = new Array();
	var videoCycleComplete = false;
	for (let i = 0, len = chunks.length; i < len; ++i) {
		video[i] = document.createElement("video");
		video[i].src = chunks[i];
		video[i].preload = 'auto';
		video[i].load();
		video[i].autoPlay = false;
		video[i].loop = false;
		video[i].muted = true;
		video[i].currentTime = 0;
		video[i].playbackRate = 16;
		videoContainer[i] = {
			video : video[i],
			ready : false
		};
		video[i].oncanplay = function(){
			videoContainer[i].scale = Math.min( canvas.width / this.videoWidth, canvas.height / this.videoHeight ); 
			videoContainer[i].ready = true;
		};
	}


	i = 0;
	function updateCanvas(){
		ctx.clearRect(0,0,canvas.width,canvas.height);
		// ignore broken video chunks
		if(videoContainer[i] !== undefined && videoContainer[i].ready){ 
			// prepare video size for playback
			var scale = videoContainer[i].scale;
			var vidH = videoContainer[i].video.videoHeight;
			var vidW = videoContainer[i].video.videoWidth;
			var top = canvas.height / 2 - (vidH /2 ) * scale;
			var left = canvas.width / 2 - (vidW /2 ) * scale;
			// manual frame-by-frame for iOS devices
			if(iOSDevice == true) videoContainer[i].video.currentTime = videoContainer[i].video.currentTime + 0.03
			// TO-DO: for the math with differance in video and canvas player FPS // videoContainer[i].video.defaultPlaybackRate = 2.0;
			// draw current video chunk frame to the canvas
			if (videoCycleComplete || iOSDevice){
				ctx.drawImage(videoContainer[i].video, left, top, vidW * scale, vidH * scale);
			}
			// switch to next video chunk
			if (videoContainer[i].video.currentTime >= (videoContainer[i].video.duration)){
				playNext();
			}
		}
		// request the next available/live frame in 25 fps
		setTimeout(updateCanvas, 1000/25);
	}

	videoContainer[0].video.play();
    updateCanvas();
	function playNext(){
		// stop the video chunk and reset to beginning
		videoContainer[i].video.pause();
		videoContainer[i].video.playbackRate = 1;
		videoContainer[i].video.currentTime = 0;
		// load next video chunk
		i++;
		if (i == chunks.length){
			i = 0;
			if (!videoCycleComplete) $('#loading').fadeOut( 500 );
			videoCycleComplete = true;
		}
		videoContainer[i].video.play();
		//videoContainer[i].video.addEventListener('ended', playNext); // backup method
	}

</script>