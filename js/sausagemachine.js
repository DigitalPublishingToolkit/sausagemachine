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
