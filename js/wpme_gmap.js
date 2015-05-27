(function (w) {	

	/**************************************************************************************************
			GOOGLE MAPS
			FUNCTIONS & DATA
	**************************************************************************************************/

	w.gmap = {
		statuses: [ 'CHECKED', 'OK', 'MISLOCATED', 'DRAFT', 'UNAVAILABLE' ],
		types: [ 'ENTERTAINMENT', 'FACTORY', 'HOSPITAL', 'HOTEL', 'HOUSE', 'LANDSCAPE', 'INDUSTRIAL', 'MILITARY', 'OFFICE', 'RELIGION', 'SCHOOL', 'UNSPECIFIED', 'RUIN', 'UNAVAILABLE' ],
		periods: [ 'ANYTIME', 'SPRING', 'SUMMER', 'AUTUMN', 'WINTER' ],
		difficulties: [ 1, 2, 3, 4, 5 ],
		ratings: [ 1, 2, 3, 4, 5 ],
	};

	var map;
	var icon_pin;
	var icon_pin_selected;
	var icon_pin_exclamation;
	var icon_pin_draggable;
	var icons_status = {};
	var icons_type = {};
	var icons_period = {};
	var plugin_url = '/wp-content/plugins/wp-map-editor';

	w.gmap.onInit = function(div, init, click) {
		google.maps.event.addDomListener(window, 'load', function () {
			map = new google.maps.Map(document.getElementById(div), {
				mapTypeId: google.maps.MapTypeId.TERRAIN,
				center: { lat: 35.682839, lng: 139.682600 },
				zoom: 8
			});
			google.maps.event.addListener(map, 'click', function() {
				click();
			});
			icon_pin = {
				url: plugin_url + '/icons/pin.png',
				size: new google.maps.Size(24, 24),
				scaledSize: new google.maps.Size(24, 24)
			};
			icon_pin_selected = {
				url: plugin_url + '/icons/selected.png',
				size: new google.maps.Size(24, 24),
				scaledSize: new google.maps.Size(24, 24)
			};
			icon_pin_exclamation = {
				url: plugin_url + '/icons/exclamation.png',
				size: new google.maps.Size(24, 24),
				scaledSize: new google.maps.Size(24, 24)
			};
			icon_pin_draggable = {
				url: plugin_url + '/icons/draggable.png',
				size: new google.maps.Size(24, 24),
				scaledSize: new google.maps.Size(24, 24)
			};
			for (var i in w.gmap.statuses) {
				var st = w.gmap.statuses[i];
				icons_status[st] = {
					url: plugin_url + '/icons/' + st + '.png',
					size: new google.maps.Size(24, 24),
					scaledSize: new google.maps.Size(24, 24)
				};
			}
			for (var i in w.gmap.types) {
				var tp = w.gmap.types[i];
				icons_type[tp] = {
					url: plugin_url + '/icons/' + tp + '.png',
					size: new google.maps.Size(24, 24),
					scaledSize: new google.maps.Size(24, 24)
				};
			}
			for (var i in w.gmap.periods) {
				var pe = w.gmap.periods[i];
				icons_period[pe] = {
					url: plugin_url + '/icons/' + pe + '.png',
					size: new google.maps.Size(24, 24),
					scaledSize: new google.maps.Size(24, 24)
				};
			}
			init();
		});
	}

	w.gmap.getCenter = function() {
		var latlng = map.getCenter();
		return latlng.lat() + "," + latlng.lng();
	}

	w.gmap.show = function(location) {
		location.visible = true;
		location.marker.setVisible(true);
	}

	w.gmap.hide = function(location) {
		location.visible = true;
		location.marker.setVisible(false);
	}

	// Mode: 'type', 'status', 'period'
	// Value: Depends on the type
	w.gmap.setLocationIcon = function(location, mode) {
		if (!location) {
			console.debug("Location is null.");
		}
		else if (location.selected) {
			location.marker.setIcon(icon_pin_selected);
		}
		else if (mode === 'status' && icons_status[location.status]) {
			location.marker.setIcon(icons_status[location.status]);
		}
		else if (mode === 'type' && icons_type[location.type]) {
			location.marker.setIcon(icons_type[location.type]);
		}
		else if (mode === 'period') {
			if (icons_period[location.period])
				location.marker.setIcon(icons_period[location.period]);
			else
				location.marker.setIcon(icon_pin);
		}
		else {
			location.marker.setIcon(icon_pin_exclamation);
		}
	}

	w.gmap.bounce = function(location) {
		location.marker.setAnimation(google.maps.Animation.BOUNCE);
		setTimeout(function() {
			location.marker.setAnimation(null); 
		}, 750);
	}

	w.gmap.setDraggable = function(location, mode, isTrue, fn) {
		if (!location) {
			console.debug("Location is null.");
		}
		if (isTrue) {
			location.marker.setIcon(icon_pin_draggable);
			location.marker.listenerDrag = google.maps.event.addListener(location.marker, 'dragend', function() {
				var latlng = location.marker.getPosition();
				fn(latlng.lat() + "," + latlng.lng(), latlng);
			});
		}
		else {
			w.gmap.setLocationIcon(location, mode);
			google.maps.event.removeListener(location.marker.listenerDrag);
		}
		location.marker.setDraggable(isTrue);
	}

	w.gmap.update = function(location, mode) {
		location.marker.setTitle(location.name);
		location.marker.setPosition(location.latlng);
		w.gmap.setLocationIcon(location, mode);
	}

	w.gmap.add = function(location, mode, mouseover, mouseout, click) {
		location.marker = new google.maps.Marker({
			position: location.latlng,
			map: map, 
			title: location.name,
			clickable: true
		});
		w.gmap.setLocationIcon(location, mode);
		location.visible = true;
		google.maps.event.addListener(location.marker, 'mouseover', function() {
			mouseover(location);
		});
		google.maps.event.addListener(location.marker, 'mouseout', function() {
			mouseout(location);
		});
		google.maps.event.addListener(location.marker, 'click', function() {
			map.panTo(location.marker.getPosition())
			click(location);
		});
	}

	w.gmap.fitbounds = function(locations) {
		var bounds = new google.maps.LatLngBounds();;
		for (var i in locations) {
			if (locations[i] && locations[i].latlng) {
				bounds.extend(locations[i].latlng);
			}
			else {
				console.debug("Location and latlng not found.", i, locations[i]);
			}
		}
		map.fitBounds(bounds);
	}

	w.gmap.remove = function(location) {
		location.marker.setMap(null);
	}

}(window));
