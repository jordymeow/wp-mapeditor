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
		$scope.isLoadingMap = false;
		$scope.mapSelectMode = 'single'; // single, multiple

		$scope.constants = { 
			statuses: gmap.statuses,
			periods: gmap.periods,
			types: gmap.types,
			difficulties: gmap.difficulties,
			ratings: gmap.ratings
		};

		$scope.editor = {
			hoveredLocation: null,
			selectedLocation: null,
			editLocation: null, // location being edited
			distanceFromSelected: null
		};

		/**************************************************************************************************
			LOAD MAPS
		**************************************************************************************************/	

		function loadMaps() {
			$http.post(ajaxurl, { 
				'action': 'load_maps'
			}).success(function (reply) {
				var maps = [];
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
		}

		function getMap(id) {
			for (var i in $scope.maps) {
				if (parseInt($scope.maps[i].id) === parseInt(id)) {
					return $scope.maps[i];
				}
			}
			return null;
		}

		function mapClear(map) {
			for (var i in $scope.locations) {
				var loc = $scope.locations[i];
				console.debug($scope.mapSelectMode, map);
				if (loc && (!map || parseInt(loc.mapId) === parseInt(map.id))) {
					gmap.remove(loc);
					delete $scope.locations[i];
					$scope.locationsCount--;
				}
			}
		}

		$scope.toggleSelectMode = function () {
			if ($scope.mapSelectMode === 'single')
				$scope.mapSelectMode = 'multiple';
			else
				$scope.mapSelectMode = 'single';
		}

		$scope.mapSelect = function (map) {
			var map = map;
			$scope.isLoadingMap = true;
			if ($scope.mapSelectMode === 'single') {
				mapClear();
			}
			else if ($scope.mapSelectMode === 'multiple' && !map.ticked) {
				mapClear(map);
			}
			if (!$scope.locationsCount) {
				$scope.isFitBounded = false;
			}
			if (map.ticked) {
				$http.post( ajaxurl, {
					action: 'load_locations',
					term_id: map.id
				}).success(function (reply) {
					$scope.mostRecentMapId = map.id;
					var data = angular.fromJson(reply.data);
					for (var i in data) {
						var m = data[i];
						updateLocation(m, map);
					}
					if (!$scope.isFitBounded && data.length > 0) {
						$scope.isFitBounded = true;
						gmap.fitbounds($scope.locations);
					}
					$scope.isLoadingMap = false;
				}).error(function (reply, status, headers) {
					$log.error({ reply: reply });
					$scope.isLoadingMap = false;
				});
			}
			else {
				$scope.isLoadingMap = false;
			}
		};

		/**************************************************************************************************
			ADD LOCATION
		**************************************************************************************************/

		// Display the popup
		$scope.onAddLocationClick = function () {
			mapOnClick();
			$scope.isAddingLocation = true;
			$scope.isEditingLocation = false;
			$scope.editor.editLocation = {
				name: "",
				description: "",
				coordinates: gmap.getCenter(),
				status: "DRAFT",
				type: "UNSPECIFIED",
				period: "ANYTIME",
				difficulty: null,
				rating: null,
				mapId: parseInt($scope.mostRecentMapId)
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
					updateLocation(reply.data);
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

		/**************************************************************************************************
			EDIT OR DELETE LOCATION
		**************************************************************************************************/

		// Display the popup
		$scope.onEditLocationClick = function () {
			$scope.isEditingLocation = true;
			$scope.isAddingLocation = false;
			copyEditLocation();
			jQuery('#wpme-modal-location').modal('show');
		}

		// Actually modify the location
		$scope.editLocation = function () {
			$scope.isSavingLocation = true;
			$http.post(ajaxurl, { 
				'action': 'edit_location',
				'location': angular.toJson($scope.editor.editLocation)
			}).success(function (reply) {
				$scope.isSavingLocation = false;
				var reply = angular.fromJson(reply);
				if (reply.success) {
					updateLocation(reply.data);
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

		// Actually modify the location
		$scope.deleteLocation = function () {
			$scope.isSavingLocation = true;
			$http.post(ajaxurl, { 
				'action': 'delete_location',
				'id': $scope.editor.selectedLocation.id
			}).success(function (reply) {
				$scope.isSavingLocation = false;
				var reply = angular.fromJson(reply);
				if (reply.success) {
					gmap.remove($scope.locations[$scope.editor.selectedLocation.id]);
					delete $scope.locations[$scope.editor.selectedLocation.id];
					$scope.locationsCount--;
					$scope.editor.selectedLocation = null;
					jQuery('#wpme-modal-location').modal('hide');
				}
				else {
					alert(reply.message);
				}
			}).error(function (reply, status, headers) {
				$scope.isSavingLocation = false;
				$log.error({ reply: reply });
				alert("Error.");
			});
		};

		/**************************************************************************************************
			DRAG
		**************************************************************************************************/

		$scope.startDraggable = function () {
			copyEditLocation();
			$scope.isDragging = true;
			gmap.setDraggable($scope.editor.selectedLocation, $scope.displayMode, true, function (coordinates, latlng) {
				$scope.editor.editLocation.coordinates = coordinates;
				$scope.editor.editLocation.latlng = latlng;
				$scope.$apply();
			});
		}

		$scope.saveDraggable = function () {
			gmap.setDraggable($scope.editor.selectedLocation, $scope.displayMode);
			$scope.isSavingLocation = true;
			$http.post(ajaxurl, { 
				'action': 'edit_location',
				'location': angular.toJson($scope.editor.editLocation)
			}).success(function (reply) {
				$scope.isSavingLocation = false;
				$scope.isDragging = false;
				var reply = angular.fromJson(reply);
				if (reply.success) {
					var map = getMap($scope.editor.editLocation.mapId);
					console.debug(map);
					updateLocation(reply.data, map);
				}
				else {
					alert(reply.message);
				}
			}).error(function (reply, status, headers) {
				$scope.isSavingLocation = false;
				$scope.isDragging = false;
				$log.error({ reply: reply });
				alert("Error.");
			});

		}

		/**************************************************************************************************
			VIEW MODE / SEARCH
		**************************************************************************************************/	

		$scope.setDisplayMode = function (mode) {
			if (mode !== 'status' && mode !== 'type' && mode !== 'period') {
				alert("Status " + mode + " not recognized.");
			}
			$scope.displayMode = mode;
			for (var i in $scope.locations) {
				gmap.setLocationIcon(	$scope.locations[i], mode );
			}
		};

		/**************************************************************************************************
			GENERAL FUNCTIONS
		**************************************************************************************************/

		function copyEditLocation() {
			$scope.editor.editLocation = {
				id: $scope.editor.selectedLocation.id,
				description: $scope.editor.selectedLocation.description,
				name: $scope.editor.selectedLocation.name,
				coordinates: $scope.editor.selectedLocation.coordinates,
				status: $scope.editor.selectedLocation.status,
				type: $scope.editor.selectedLocation.type,
				period: $scope.editor.selectedLocation.period,
				difficulty: $scope.editor.selectedLocation.difficulty,
				rating: $scope.editor.selectedLocation.rating,
				mapId: $scope.editor.selectedLocation.mapId
			};
		}

		// Update location from a json location from the server
		var updateLocation = function(location, map) {
			if (!location.coordinates) {
				$log.warn("Location has not coordinates", location);
				return;
			}
			if (!map) {
				map = getMap(location.mapId);
				if (!map) {
					$log.warn("updateLocation requires a map or mapId", location, map);
					return;
				}
			}
			var isNew = !$scope.locations[location.id];
			var gps = location.coordinates.split(',');
			if (location.coordinates && gps.length === 2) {
				if (isNew) {
					$scope.locations[location.id] = {
						selected: false
					};
				}
				angular.extend($scope.locations[location.id], {
					id: parseInt(location.id), 
					mapId: parseInt(map.id), 
					mapName: map.name,
					description: location.description,
					name: location.name, 
					coordinates: location.coordinates,
					type: location.type, 
					period: location.period, 
					status: location.status,
					rating: location.rating, 
					difficulty: location.difficulty,
					latlng:  new google.maps.LatLng(gps[0].trim(), gps[1].trim())
				});
				if (isNew) {
					$scope.locationsCount++;
					gmap.add($scope.locations[location.id], $scope.displayMode, markerOnMouseOver, markerOnMouseOut, markerOnClick);
				}
				else {
					gmap.update($scope.locations[location.id], $scope.displayMode);
				}
			}
			else {
				$log.warn("Location has not coordinates", location);
			}
		}

		/**************************************************************************************************
			LISTENERS
		**************************************************************************************************/

		var mapOnClick = function () {
			if ($scope.editor.selectedLocation) {
				if ($scope.isDragging) {
					gmap.update($scope.editor.selectedLocation); // Need to reset the location
					$scope.isDragging = false;
				}
				$scope.editor.selectedLocation.selected = false;
				gmap.setLocationIcon($scope.editor.selectedLocation, $scope.displayMode);
				$scope.editor.selectedLocation = null;
				$scope.$apply();
			}
		}

		var markerOnMouseOver = function (location) {
			$scope.editor.hoveredLocation = location;
			if ($scope.editor.selectedLocation) {
				$scope.editor.distanceFromSelected = Math.round(getDistanceFromLatLonInKm(
					$scope.editor.selectedLocation.latlng.lat(), $scope.editor.selectedLocation.latlng.lng(),
					$scope.editor.hoveredLocation.latlng.lat(), $scope.editor.hoveredLocation.latlng.lng())) + " km";
			}
			$scope.$apply();
		}

		var markerOnMouseOut = function (location) {
			$scope.editor.hoveredLocation = null;
			$scope.editor.distanceFromSelected = null;
			$scope.$apply();
		}

		var markerOnClick = function (location) {
			mapOnClick();
			$scope.editor.selectedLocation = location;
			$scope.editor.selectedLocation.selected = true;
			gmap.setLocationIcon($scope.editor.selectedLocation, $scope.displayMode);
			$scope.editor.distanceFromSelected = null;
			$scope.$apply();
		}

		var activeCurrentPosition = function () {
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

		/**************************************************************************************************
			INIT
		**************************************************************************************************/

		gmap.onInit('wpme-map', function (mapOnClick) {
			$scope.gmapLoaded = true;
			$scope.$apply();
			loadMaps();
		}, mapOnClick);

	}

})();
