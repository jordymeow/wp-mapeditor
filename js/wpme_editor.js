(function () {

	"use strict";

	angular.module('MapEditor', [ "isteven-multi-select" ]);
	angular.module('MapEditor')
	.controller("EditorCtrl", EditorCtrl);	

	/**************************************************************************************************
			GOOGLE MAPS
			FUNCTIONS & DATA
	**************************************************************************************************/

	var gmap;
	var icon_pin;
	var plugin_url = '/wp-content/plugins/wp-map-editor';

	function init_gmap(click) {
		gmap = new google.maps.Map(document.getElementById('wpme-map'), {
			center: { lat: 35.682839, lng: 139.682600 },
			zoom: 4
		});
		google.maps.event.addListener(gmap, 'click', function() {
			click();
		});
		icon_pin = {
			url: plugin_url + '/icons/blackpin.png',
			size: new google.maps.Size(32, 32),
			scaledSize: new google.maps.Size(16, 16)
		};
	}

	function gmap_show(location) {
		location.visible = true;
		location.marker.setVisible(true);
	}

	function gmap_hide(location) {
		location.visible = true;
		location.marker.setVisible(false);
	}

	function gmap_add(location, mouseover, mouseout, click) {
		location.marker = new google.maps.Marker({
			position: location.latlng,
			map: gmap, 
			name: location.name,
			clickable: true,
			icon: icon_pin
		});
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
			bounds.extend(locations[i].latlng);
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

		$scope.editor = {
			hoveredLocation: null,
			selectedLocation: null
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
				maps.push({ id: m.id, name: m.name, ticked: false });
			});
			$scope.maps = maps;
		});

		$scope.mapOnClick = function () {
			$scope.editor.selectedLocation = null;
			$scope.$apply();
		}

		$scope.markerOnMouseOver = function (location) {
			$scope.editor.hoveredLocation = location;
			$scope.$apply();
		}

		$scope.markerOnMouseOut = function (location) {
			$scope.editor.hoveredLocation = null;
			$scope.$apply();
		}

		$scope.markerOnClick = function (location) {
			$scope.editor.selectedLocation = location;
			$scope.$apply();
		}

		$scope.mapSelect = function (map) {
			var map = map;
			console.debug(map);
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
							status: m.status,
							rating: m.rating,
							difficulty: m.difficulty,
							// Extra
							latlng:  new google.maps.LatLng(gps[0], gps[1]),
							visible: false
						};
						gmap_add($scope.locations[m.id], $scope.markerOnMouseOver, $scope.markerOnMouseOut, $scope.markerOnClick);
					}
					gmap_center($scope.locations);
				});
			}
			else {
				for (var i in $scope.locations) {
					var m = $scope.locations[i];
					if (m.mapId == map.id) {
						gmap_remove($scope.locations[m.id]);
						$scope.locations[m.id] = undefined;
					}
				}
			}
		};
	}

})();
