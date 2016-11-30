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

// Start at the first tab if 'QUERY_STRING' is not set
$query = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : 'import';
// use the first URL argument for the router
$pos = strpos($query, '&');
if ($pos !== false) {
	$query = substr($query, 0, $pos);
} else if (empty($query)) {
	$query = config('default_route', '');
}

// render view and return HTML
if (@is_file('view-' . $query . '.php')) {
	echo render_php('view-' . $query . '.php');
} else {
	router_error_400('Route ' . $query . ' does not exist');
}
