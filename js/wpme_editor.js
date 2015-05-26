(function () {

	"use strict";

	angular.module('MapEditor', [ "isteven-multi-select", "angular-ladda" ]);
	angular.module('MapEditor')
	.controller("EditorCtrl", EditorCtrl)
	.config(['$httpProvider', function ($httpProvider) {
		// Intercept POST requests, convert to standard form encoding
		$httpProvider.defaults.headers.post["Content-Type"] = "application/x-www-form-urlencoded";
		$httpProvider.defaults.transformRequest.unshift(function (data, headersGetter) {
			var key, result = [];
			for (key in data) {
				if (data.hasOwnProperty(key)) {
				result.push(encodeURIComponent(key) + "=" + encodeURIComponent(data[key]));
				}
			}
			return result.join("&");
		});
	}]);

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
		return deg * (Math.PI / 180)
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
	var types = [ 'ENTERTAINMENT', 'FACTORY', 'HOSPITAL', 'HOTEL', 'HOUSE', 'LANDSCAPE', 'INDUSTRIAL', 'MILITARY', 'OFFICE', 'RELIGION', 'SCHOOL', 'UNSPECIFIED', 'RUIN', 'UNAVAILABLE' ];
	var periods = [ 'ANYTIME', 'SPRING', 'SUMMER', 'AUTUMN', 'WINTER' ];
	var ratings = [ 1, 2, 3, 4, 5 ];
	var difficulties = [ 1, 2, 3, 4, 5 ];
	var icons_status = {};
	var icons_type = {};
	var icons_period = {};
	var plugin_url = '/wp-content/plugins/wp-map-editor';

	function init_gmap(click) {
		gmap = new google.maps.Map(document.getElementById('wpme-map'), {
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

	function gmap_getCenter() {
		var latlng = gmap.getCenter();
		return latlng.lat() + "," + latlng.lng();
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

	function gmap_update(location, mode) {
		location.marker.setTitle(location.name);
		location.marker.setPosition(location.latlng);
		gmap_setLocationIcon(location, mode);
	}

	function gmap_add(location, mode, mouseover, mouseout, click) {
		location.marker = new google.maps.Marker({
			position: location.latlng,
			map: gmap, 
			title: location.name,
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

	function gmap_fitbounds(locations) {
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

	function EditorCtrl($scope, $location, $timeout, $filter, $log, $http) {
		$scope.maps = [];
		$scope.selectedMaps = [];
		$scope.mostRecentMapId = [];
		$scope.locations = {};
		$scope.locationsCount = 0;
		$scope.gmapLoaded = false;
		$scope.displayMode = 'status';
		$scope.isFitBounded = false;
		$scope.isAddingLocation = false;
		$scope.isEditingLocation = false;
		$scope.isSavingLocation = false;

		$scope.constants = { 
			statuses: statuses,
			periods: periods,
			types: types,
			difficulties: difficulties,
			ratings: ratings
		};

		var maps = [];

		$scope.editor = {
			hoveredLocation: null,
			selectedLocation: null,
			editLocation: null, // location being edited
			distanceFromSelected: null
		};

		google.maps.event.addDomListener(window, 'load', function () {
			init_gmap($scope.mapOnClick);
			$scope.gmapLoaded = true;
			$scope.$apply();
		});

		$scope.activeCurrentPosition = function () {
			navigator.geolocation.getCurrentPosition(function (pos) {
				var crd = pos.coords;
				console.log('Your current position is: ', crd.latitude, crd.longitude);
			}, function () {
				console.warn('ERROR(' + err.code + '): ' + err.message);
			}, {
				enableHighAccuracy: true,
				timeout: 5000,
				maximumAge: 0
			});
		}

		$http.post(ajaxurl, { 
			'action': 'load_maps'
		}).success(function (reply) {
			var data = angular.fromJson(reply.data);
			angular.forEach(data, function (m) {
				maps.push({ id: m.id, name: m.name, ticked: m.ticked });
				if (m.ticked) {
					$scope.mapSelect(m);
				}
			});
			$scope.maps = maps;
		}).error(function (reply, status, headers) {
			$log.error({ reply: reply });
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
			if ($scope.editor.selectedLocation) {
				$scope.editor.selectedLocation.selected = false;
				gmap_setLocationIcon($scope.editor.selectedLocation, $scope.displayMode);
				$scope.editor.selectedLocation = null;
			}
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
				$scope.editor.selectedLocation.selected = false;
				gmap_setLocationIcon($scope.editor.selectedLocation, $scope.displayMode);
			}
			$scope.editor.selectedLocation = location;
			$scope.editor.selectedLocation.selected = true;
			gmap_setLocationIcon($scope.editor.selectedLocation, $scope.displayMode);
			$scope.editor.distanceFromSelected = null;
			$scope.$apply();
		}
		// Display the popup
		$scope.onAddLocationClick = function () {
			$scope.mapOnClick();
			$scope.isAddingLocation = true;
			$scope.isEditingLocation = false;
			$scope.editor.editLocation = {
				name: "",
				description: "",
				coordinates: gmap_getCenter(),
				mapId: "",
				status: "DRAFT",
				type: "UNSPECIFIED",
				description: "",
				period: "ANYTIME",
				difficulty: null,
				rating: null,
				mapId: $scope.mostRecentMapId
			};
			jQuery('#wpme-modal-location').modal('show');
		}

		// Actually modify the location
		$scope.addLocation = function () {
			$scope.isSavingLocation = true;
			$http.post(ajaxurl, { 
				'action': 'add_location',
				'location': angular.toJson($scope.editor.editLocation)
			}).success(function (reply) {
				$scope.isSavingLocation = false;
				var reply = angular.fromJson(reply);
				if (reply.success) {
					$scope.locationSet(reply.data);
					jQuery('#wpme-modal-location').modal('hide');
				}
				else {
					jQuery('#wpme-modal-location').modal('hide');
					alert(reply.message);
				}
			}).error(function (reply, status, headers) {
				jQuery('#wpme-modal-location').modal('hide');
				$log.error({ reply: reply });
				alert("Error.");
			});
		};

		// Display the popup
		$scope.onEditLocationClick = function () {
			$scope.isEditingLocation = true;
			$scope.isAddingLocation = false;
			$scope.editor.editLocation = {
				id: $scope.editor.selectedLocation.id,
				description: $scope.editor.selectedLocation.description,
				name: $scope.editor.selectedLocation.name,
				coordinates: $scope.editor.selectedLocation.coordinates,
				status: $scope.editor.selectedLocation.status,
				type: $scope.editor.selectedLocation.type,
				description: $scope.editor.selectedLocation.description,
				period: $scope.editor.selectedLocation.period,
				difficulty: $scope.editor.selectedLocation.difficulty,
				rating: $scope.editor.selectedLocation.rating,
				mapId: $scope.editor.selectedLocation.mapId
			};
			jQuery('#wpme-modal-location').modal('show');
		}

		// Update location from a json location from the server
		$scope.locationSet = function(location) {
			var isNew = !$scope.locations[location.id];
			var gps = location.coordinates.split(',');
			if (location.coordinates && gps.length === 2) {
				if (isNew) {
					$scope.locations[location.id] = {};
				}
				angular.extend($scope.locations[location.id], {
					id: location.id, 
					mapId: map.id, 
					mapName: map.name,
					description: location.description,
					name: location.name, 
					coordinates: location.coordinates,
					type: location.type, 
					period: location.period, 
					status: location.status,
					rating: location.rating, 
					difficulty: location.difficulty,
					// Extra
					latlng:  new google.maps.LatLng(gps[0], gps[1]),
					selected: false,
					visible: false
				});
				if (isNew) {
					$scope.locationsCount++;
					gmap_add($scope.locations[location.id], $scope.displayMode, $scope.markerOnMouseOver, $scope.markerOnMouseOut, $scope.markerOnClick);
				}
				else {
					gmap_update($scope.locations[location.id], $scope.displayMode);
				}
			}
			else {
				$log.warn("Location has not coordinates", m);
			}
		}

		// Actually modify the location
		$scope.editLocation = function () {
			$scope.isSavingLocation = true;
			$scope.isEditingLocation = false;
			$http.post(ajaxurl, { 
				'action': 'edit_location',
				'location': angular.toJson($scope.editor.editLocation)
			}).success(function (reply) {
				$scope.isSavingLocation = false;
				var reply = angular.fromJson(reply);
				if (reply.success) {
					$scope.locationSet(reply.data);
					jQuery('#wpme-modal-location').modal('hide');
				}
				else {
					alert(reply.message);
				}
			}).error(function (reply, status, headers) {
				$log.error({ reply: reply });
				alert("Error.");
			});
		};

		$scope.mapSelect = function (map) {
			var map = map;
			if (map.ticked) {
				$http.post( ajaxurl, {
					action: 'load_locations',
					term_id: map.id
				}).success(function (reply) {
					$scope.mostRecentMapId = map.id;
					var data = angular.fromJson(reply.data);
					for (var i in data) {
						var m = data[i];
						$scope.locationSet(m);
					}
					if (!$scope.isFitBounded) {
						$scope.isFitBounded = true;
						gmap_fitbounds($scope.locations);
					}
				}).error(function (reply, status, headers) {
					$log.error({ reply: reply });
				});
			}
			else {
				for (var i in $scope.locations) {
					var m = $scope.locations[i];
					if (m && m.mapId == map.id) {
						gmap_remove($scope.locations[m.id]);
						delete $scope.locations[m.id];
						$scope.locationsCount--;
					}
				}
				if (!$scope.locationsCount) {
					$scope.isFitBounded = false;
				}
			}
		};
	}

})();
