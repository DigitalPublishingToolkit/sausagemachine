<?php

/*
	Main index file for Sausage Machine
	Copyright (C) 2015  Gottfried Haider for PublishingLab

	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

@require_once('config.inc.php');
require_once('router.inc.php');
require_once('util.inc.php');


/**
 *	Return the edit view
 */
function route_get_edit($param = array()) {
	return render_php('view-edit.php');
}

/**
 *	Return the import view
 */
function route_get_import($param = array()) {
	return render_php('view-import.php');
}

/**
 *	Return the projects view
 */
function route_get_projects($param = array()) {
	return render_php('view-projects.php');
}



register_route('GET', 'edit', 'route_get_edit');
register_route('GET', 'import', 'route_get_import');
register_route('GET', 'projects', 'route_get_projects');


$query = $_SERVER['QUERY_STRING'];
// use the first URL argument for the router
$pos = strpos($query, '&');
if ($pos !== false) {
	$query = substr($query, 0, $pos);
}
if (empty($query)) {
	$query = config('default_route', '');
}
echo route($_SERVER['REQUEST_METHOD'], $query);
