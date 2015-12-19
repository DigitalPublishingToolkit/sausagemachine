/* requires jQuery */

$.sausagemachine = $.sausagemachine || {};

$.sausagemachine._clear = function() {
	sessionStorage.removeItem('state');
};

$.sausagemachine._get = function(key) {
	var data = sessionStorage.getItem('state');
	if (data === null) {
		data = {};
	} else {
		data = JSON.parse(data);
	}
	return data[key];
};

$.sausagemachine._set = function(key, val) {
	var data = sessionStorage.getItem('state');
	if (data === null) {
		data = {};
	} else {
		data = JSON.parse(data);
	}
	data[key] = val;
	sessionStorage.setItem('state', JSON.stringify(data));
};

$.sausagemachine.get_repos = function(success, error) {
	$.ajax({
		url: 'api.php?repos',
		error: function(jqXHR, textStatus, errorThrown) {
			if (typeof error === 'function') {
				error(jqXHR, textStatus, errorThrown);
			} else {
				console.error('Backend returned: ' + jqXHR.responseText);
			}
		},
		method: 'get',
		success: function(data) {
			success(data);
		},
		dataType: 'json'
	});
};

$.sausagemachine.get_repo_files = function(repo, success, error) {
	$.ajax({
		url: 'api.php?repos/files/' + repo,
		error: function(jqXHR, textStatus, errorThrown) {
			if (typeof error === 'function') {
				error(jqXHR, textStatus, errorThrown);
			} else {
				console.error('Backend returned: ' + jqXHR.responseText);
			}
		},
		method: 'get',
		success: function(data) {
			success(data.files);
		},
		dataType: 'json'
	});
};

$.sausagemachine.get_temp_files = function(temp, success, error) {
	$.ajax({
		url: 'api.php?temps/' + temp,
		error: function(jqXHR, textStatus, errorThrown) {
			if (typeof error === 'function') {
				error(jqXHR, textStatus, errorThrown);
			} else {
				console.error('Backend returned: ' + jqXHR.responseText);
			}
		},
		method: 'get',
		success: function(data) {
			success(data.files);
		},
		dataType: 'json'
	});
};

$.sausagemachine.get_targets = function(repo, success, error) {
	$.ajax({
		url: 'api.php?repos/targets/' + repo,
		error: function(jqXHR, textStatus, errorThrown) {
			if (typeof error === 'function') {
				error(jqXHR, textStatus, errorThrown);
			} else {
				console.error('Backend returned: ' + jqXHR.responseText);
			}
		},
		method: 'get',
		success: function(data) {
			if (typeof success === 'function') {
				success(data);
			}
		},
		dataType: 'json'
	});
};

$.sausagemachine.create_temp = function(opt, success, error) {
	opt = opt || {};
	$.ajax({
		url: 'api.php?temps/create',
		data: opt,
		error: function(jqXHR, textStatus, errorThrown) {
			if (typeof error === 'function') {
				error(jqXHR, textStatus, errorThrown);
			} else {
				console.error('Backend returned: ' + jqXHR.responseText);
			}
		},
		method: 'post',
		success: function(data) {
			if (typeof success === 'function') {
				success(data);
			}
		},
		dataType: 'json'
	});
};
