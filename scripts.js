function goBack() {
	// @TODO remove when users have the app version 6.0.4
	// which will have apretaste.back() defined by default
	if (typeof apretaste.back == 'undefined') {
		apretaste.send({'command':'ANUNCIOS LIST'});
	} else {
		apretaste.back();
	}
}

function getViaWeb(url) {
	apretaste.send({
		command: 'WEB',
		data: {query:url}
	});
}