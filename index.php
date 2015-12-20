<?php

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
