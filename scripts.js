function openWeb(url, isEmail) {
	if(isEmail) {
		apretaste.send({command:'WEB', data: {query:url}});
	} else {
		window.open(url, "_blank");
	}
}
