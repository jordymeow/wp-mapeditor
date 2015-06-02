<div ng-app="MapEditor" ng-controller="EditorCtrl">
<p style="position: absolute; margin: 20px; font-size: 14px; font-style: italic;">Loading Google Maps...</p>
<div id="wpme-mapeditor" class="ng-hide" ng-show="gmapLoaded">

	<nav id="wme-navbar-header" class="navbar navbar-inverse">
		<div class="container-fluid">

			<div style="position: absolute; right: 10px; margin-top: 2px;" class="logo pull-right">
				<a target="_blank" href="http://www.meow.fr"><img height="42" src="<?php echo plugin_dir_url( __FILE__ ); ?>/icons/jordy-meow.png" /></a>
			</div>

			<button type="button" ladda="isLoadingMap" class="btn btn-primary btn-sm navbar-btn pull-left" ng-click="toggleSelectMode()">
				<span ng-show="mapSelectMode === 'single'" class="glyphicon glyphicon-map-marker"></span>
				<span ng-show="mapSelectMode === 'multiple'" class="glyphicon glyphicon-globe"></span>
			</button>

			<div isteven-multi-select id="wme-map-selector" class="btn-sm" style="
					float: left;
					margin-top: 5px;
					position: relative;"
				input-model="maps"
				output-model="selectedMaps"
				helper-elements=""
				selection-mode="{{mapSelectMode}}"
				button-label="icon name"
				item-label="icon name maker"
				disable-property="disabled"
				on-item-click="mapSelect(data)"
				tick-property="ticked">
			</div>

			<div class="btn-group" ng-disabled="selectedMaps.length < 1">
				<button type="button" class="btn btn-primary btn-sm dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
					<span ng-if="displayMode === 'status'">
						<span class="glyphicon glyphicon-flag"></span> <span class="hidden-xs">Status</span> <span class="caret"></span>
					</span>
					<span  ng-if="displayMode === 'type'">
						<span class="glyphicon glyphicon-tree-conifer"></span> <span class="hidden-xs">Type</span> <span class="caret"></span>
					</span>
					<span ng-if="displayMode === 'period'">
						<span class="glyphicon glyphicon-tree-conifer"></span> <span class="hidden-xs">Period</span> <span class="caret"></span>
					</span>
				</button>
				<ul class="dropdown-menu" role="menu">
					<li><a href="#" ng-click="setDisplayMode('status')"><span class="glyphicon glyphicon-flag"></span> Status</a></li>
					<li><a href="#" ng-click="setDisplayMode('type')"><span class="glyphicon glyphicon-tree-conifer"></span> Type</a></li>
					<li><a href="#" ng-click="setDisplayMode('period')"><span class="glyphicon glyphicon-tree-conifer"></span> Period</a></li>
				</ul>
			</div>
			<button type="button" ng-disabled="selectedMaps.length < 1 || editor.selectedLocation" class="btn btn-success btn-sm navbar-btn" ng-click="onAddLocationClick()">
				<span class="glyphicon glyphicon-plus"></span> <span class="hidden-xs">Location</span>
			</button>
<!-- 			<button type="button" class="btn btn-success btn-sm navbar-btn">
				<span class="glyphicon glyphicon-asterisk"></span>
			</button> -->
		</div>

	</nav>
	<div id="wpme-info" class="ng-hide" ng-show="editor.selectedLocation">
		<div class="header">
			<span class="name">{{editor.selectedLocation.name}}</span><br />
			<span class="coordinates">
				<a target="_blank" href="https://www.google.com/maps/dir//{{editor.selectedLocation.coordinates}}/@{{editor.selectedLocation.coordinates}}">
					{{editor.selectedLocation.coordinates}}
				</a>
			</span>
		</div>
		<div class="info">
			Status: {{editor.selectedLocation.status}}<br />
			Type: {{editor.selectedLocation.type}}<br />
			Rating: {{editor.selectedLocation.rating}}<br />
			Difficulty: {{editor.selectedLocation.difficulty}}<br />
		</div>
		<div class="actions">
			<button ladda="isSavingLocation" ng-disabled="isDragging" type="button" class="btn btn-primary btn-sm navbar-btn" ng-click="onEditLocationClick()">
				<span class="glyphicon glyphicon-pencil"></span>
			</button>
			<button ladda="isSavingLocation" ng-hide="isDragging" type="button" class="btn btn-primary btn-sm navbar-btn" ng-click="startDraggable()">
				<span class="glyphicon glyphicon glyphicon-move"></span>
			</button>
			<button ladda="isSavingLocation" ng-show="isDragging" type="button" class="btn btn-primary btn-sm navbar-btn" ng-click="saveDraggable()">
				<span class="glyphicon glyphicon glyphicon-ok"></span>
			</button>
			<button ladda="isSavingLocation" ng-disabled="isDragging" type="button" class="btn btn-success btn-sm">
				<span class="glyphicon glyphicon-asterisk"></span>
			</button>
			<button ladda="isSavingLocation" ng-disabled="isDragging" type="button" class="btn btn-danger btn-sm" ng-click="deleteLocation()">
				<span class="glyphicon glyphicon-trash"></span>
			</button>
		</div>
	</div>
	<div id="wpme-map"></div>
	<nav id="wme-navbar-footer">
		{{editor.hoveredLocation.name}}
		<span class="distance" ng-show="editor.distanceFromSelected">
			({{editor.distanceFromSelected}})
		</span>
	</nav>

</div>

<div class="modal" id="wpme-modal-location">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-body">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 style="margin-top: 0px;">Location</h4>
				<form>
						<div class="row">
							<div class="col-md-8">
								<div class="form-group">
									<label>Name</label>
									<input type="text" class="form-control" id="name" placeholder="Name" ng-model="editor.editLocation.name">
								</div>
							</div>
							<div class="col-md-4">
								<div class="form-group">
									<label>Coordinates</label>
									<input type="text" class="form-control" id="coordinates" placeholder="GPS Coordinates" ng-model="editor.editLocation.coordinates">
								</div>
							</div>
					</div>
					
					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label>Status</label>
								<select id="status" class="form-control" ng-options="s as s for s in constants.statuses" ng-model="editor.editLocation.status"></select>
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label>Type</label>
								<select id="type" class="form-control" ng-options="t as t for t in constants.types" ng-model="editor.editLocation.type"></select>
							</div>
						</div>
					</div>

					<div class="form-group">
						<textarea class="form-control" id="description" rows="3" placeholder="Description" ng-model="editor.editLocation.description"></textarea>
					</div>
					<div class="form-group">
						<div class="row">
							<div class="col-md-4">
								<label>Period / Season</label>
								<select id="period" class="form-control" 
									ng-options="p as p for p in constants.periods" ng-model="editor.editLocation.period">
								</select>
							</div>
							<div class="col-md-4">
								<label>Difficulty</label>
								<select id="difficulty" class="form-control" 
									ng-options="d as d for d in constants.difficulties" ng-model="editor.editLocation.difficulty">
								</select>
							</div>
							<div class="col-md-4">
								<label>Rating</label>
								<select id="rating" class="form-control" 
									ng-options="r as r for r in constants.ratings" ng-model="editor.editLocation.rating">
								</select>
							</div>
						</div>
					</div>
				</form>
			</div>
			<div class="modal-footer">
				<button ng-show="isEditingLocation" type="button" ladda="isSavingLocation" ng-click="editLocation()" class="btn btn-primary pull-right"><span class="glyphicon glyphicon-pen"></span> Modify</button>
				<button ng-show="isAddingLocation" type="button" ladda="isSavingLocation" ng-click="addLocation()" class="btn btn-primary pull-right"><span class="glyphicon glyphicon-plus"></span> Add</button>
				<div ng-show="isAddingLocation" class="form-group pull-right">
					<select id="map" class="form-control" 
						ng-options="r.id as r.name for r in maps" ng-model="editor.editLocation.mapId"
						style="margin: 3px 13px 3px 0px; width: 200px;">
					</select>
				</div>
			</div>
		</div><!-- /.modal-content -->
	</div><!-- /.modal-dialog -->
</div><!-- /.modal -->

</div>