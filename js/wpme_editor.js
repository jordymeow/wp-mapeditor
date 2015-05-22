(function () {

	"use strict";

	angular.module('MapEditor', [ "isteven-multi-select" ]);
	angular.module('MapEditor')
	.controller("EditorCtrl", EditorCtrl);	

	/**************************************************************************************************
			VENDOR FUNCTIONS
	**************************************************************************************************/

	function getDistanceFromLatLonInKm(lat1, lon1, lat2, lon2) {
		var R = 6371; // Radius of the earth in km
		var dLat = deg2rad(lat2-lat1);  // deg2rad below
		var dLon = deg2rad(lon2-lon1); 
		var a = 
		Math.sin(dLat/2) * Math.sin(dLat/2) +
		Math.cos(deg2rad(lat1)) * Math.cos(deg2rad(lat2)) * 
		Math.sin(dLon/2) * Math.sin(dLon/2); 
		var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a)); 
		var d = R * c; // Distance in km
		return d;
	}

	function deg2rad(deg) {
		return deg * (Math.PI/180)
	}

	/**************************************************************************************************
			GOOGLE MAPS
			FUNCTIONS & DATA
	**************************************************************************************************/

	var gmap;
	var icon_pin;
	var icon_pin_selected;
	var icon_pin_exclamation;
	var statuses = [ 'CHECKED', 'OK', 'MISLOCATED', 'DRAFT', 'UNAVAILABLE' ];
	var types = [ 'ENTERTAINMENT', 'FACTORY', 'HOSPITAL', 'HOTEL', 'HOUSE', 'LANDSCAPE', 'INDUSTRIAL', 'MILITARY', 'OFFICE', 'RELIGION', 'SCHOOL', 'UNSPECIFIED', 'UNAVAILABLE' ];
	var periods = [ 'SPRING', 'SUMMER', 'AUTUMN', 'WINTER' ];
	var icons_status = {};
	var icons_type = {};
	var icons_period = {};
	var plugin_url = '/wp-content/plugins/wp-map-editor';

	function init_gmap(click) {
		gmap = new google.maps.Map(document.getElementById('wpme-map'), {
			animatedZoom: false,
			mapTypeId: google.maps.MapTypeId.TERRAIN,
			center: { lat: 35.682839, lng: 139.682600 },
			zoom: 4
		});
		google.maps.event.addListener(gmap, 'click', function() {
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
		for (var i in statuses) {
			var st = statuses[i];
			icons_status[st] = {
				url: plugin_url + '/icons/' + st + '.png',
				size: new google.maps.Size(24, 24),
				scaledSize: new google.maps.Size(24, 24)
			};
		}
		for (var i in types) {
			var tp = types[i];
			icons_type[tp] = {
				url: plugin_url + '/icons/' + tp + '.png',
				size: new google.maps.Size(24, 24),
				scaledSize: new google.maps.Size(24, 24)
			};
		}
		for (var i in periods) {
			var pe = periods[i];
			icons_period[pe] = {
				url: plugin_url + '/icons/' + pe + '.png',
				size: new google.maps.Size(24, 24),
				scaledSize: new google.maps.Size(24, 24)
			};
		}
	}

	function gmap_show(location) {
		location.visible = true;
		location.marker.setVisible(true);
	}

	function gmap_hide(location) {
		location.visible = true;
		location.marker.setVisible(false);
	}

	// Mode: 'type', 'status', 'period'
	// Value: Depends on the type
	function gmap_setLocationIcon(location, mode) {
		if (mode === 'status' && icons_status[location.status]) {
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
			console.debug("gmap_setLocationIcon: could not find", mode, location.status);
			location.marker.setIcon(icon_pin_exclamation);
		}
	}

	function gmap_setLocationAsSelected(location) {
		location.marker.setIcon(icon_pin_selected);
	}

	function gmap_add(location, mode, mouseover, mouseout, click) {
		location.marker = new google.maps.Marker({
			position: location.latlng,
			map: gmap, 
			name: location.name,
			clickable: true
		});
		gmap_setLocationIcon(location, mode);
		location.visible = true;
		google.maps.event.addListener(location.marker, 'mouseover', function() {
			mouseover(location);
		});
		google.maps.event.addListener(location.marker, 'mouseout', function() {
			mouseout(location);
		});
		google.maps.event.addListener(location.marker, 'click', function() {
			gmap.panTo(location.marker.getPosition())
			click(location);
		});
	}

	function gmap_center(locations) {
		var bounds = new google.maps.LatLngBounds();;
		for (var i in locations) {
			if (locations[i] && locations[i].latlng) {
				bounds.extend(locations[i].latlng);
			}
			else {
				console.debug("Location and latlng not found.", i, locations[i]);
			}
		}
		gmap.fitBounds(bounds);
	}

	function gmap_remove(location) {
		location.marker.setMap(null);
	}

	/**************************************************************************************************
			EDITOR CONTROLLER
	**************************************************************************************************/

	function EditorCtrl($scope, $location, $timeout, $filter) {
		$scope.maps = [];
		$scope.selectedMaps = [];
		$scope.locations = {};
		$scope.locationsCount = 0;
		$scope.gmapLoaded = false;
		$scope.displayMode = 'status';

		$scope.editor = {
			hoveredLocation: null,
			selectedLocation: null,
			distanceFromSelected: null
		};

		google.maps.event.addDomListener(window, 'load', function () {
			init_gmap($scope.mapOnClick);
			$scope.gmapLoaded = true;
			$scope.$apply();
		});

		jQuery.post( ajaxurl, { action: 'load_maps' }, function(response) {
			var maps = [];
			var data = angular.fromJson(response);
			angular.forEach(data, function (m) {
				maps.push({ id: m.id, name: m.name, ticked: m.ticked });
				if (m.ticked) {
					$scope.mapSelect(m);
				}

			});
			$scope.maps = maps;
			$scope.$apply();
		});

		$scope.setDisplayMode = function (mode) {
			if (mode !== 'status' && mode !== 'type' && mode !== 'period') {
				alert("Status " + mode + " not recognized.");
			}
			$scope.displayMode = mode;
			for (var i in $scope.locations) {
				gmap_setLocationIcon(	$scope.locations[i], mode );
			}
		};

		$scope.mapOnClick = function () {
			gmap_setLocationIcon($scope.editor.selectedLocation, $scope.displayMode);
			$scope.editor.selectedLocation = null;
			$scope.$apply();
		}

		$scope.markerOnMouseOver = function (location) {
			$scope.editor.hoveredLocation = location;
			if ($scope.editor.selectedLocation) {
				$scope.editor.distanceFromSelected = Math.round(getDistanceFromLatLonInKm(
					$scope.editor.selectedLocation.latlng.lat(), $scope.editor.selectedLocation.latlng.lng(),
					$scope.editor.hoveredLocation.latlng.lat(), $scope.editor.hoveredLocation.latlng.lng())) + " km";
			}
			$scope.$apply();
		}

		$scope.markerOnMouseOut = function (location) {
			$scope.editor.hoveredLocation = null;
			$scope.editor.distanceFromSelected = null;
			$scope.$apply();
		}

		$scope.markerOnClick = function (location) {
			if ($scope.editor.selectedLocation) {
				gmap_setLocationIcon($scope.editor.selectedLocation, $scope.displayMode);
			}
			$scope.editor.selectedLocation = location;
			$scope.editor.distanceFromSelected = null;
			gmap_setLocationAsSelected(location);
			$scope.$apply();
		}

		$scope.mapSelect = function (map) {
			var map = map;
			if (map.ticked) {
				jQuery.post( ajaxurl, {
					'action': 'load_locations',
					'term_id': map.id
				}, 
				function(response) {
					var data = angular.fromJson(response);
					for (var i in data) {
						var m = data[i];
						var gps = m.coordinates.split(',');
						$scope.locations[m.id] = {
							// From Data
							id: m.id,
							mapId: map.id,
							mapName: map.name,
							name: m.name,
							coordinates: m.coordinates,
							type: m.type,
							period: m.period,
							status: m.status,
							rating: m.rating,
							difficulty: m.difficulty,
							// Extra
							latlng:  new google.maps.LatLng(gps[0], gps[1]),
							visible: false
						};
						gmap_add($scope.locations[m.id], $scope.displayMode, $scope.markerOnMouseOver, $scope.markerOnMouseOut, $scope.markerOnClick);
					}
					gmap_center($scope.locations);
				});
			}
			else {
				for (var i in $scope.locations) {
					var m = $scope.locations[i];
					if (m && m.mapId == map.id) {
						gmap_remove($scope.locations[m.id]);
						delete $scope.locations[m.id];
					}
					if (!m) {
						console.debug(m);
					}
				}
			}
		};
	}

})();
