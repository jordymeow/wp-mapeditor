function initialize() {
	var mapOptions = {
		center: { lat: 35.682839, lng: 139.682600 },
		zoom: 8
	};
	var map = new google.maps.Map(document.getElementById('wpme-map'), mapOptions);
}

google.maps.event.addDomListener(window, 'load', initialize);
