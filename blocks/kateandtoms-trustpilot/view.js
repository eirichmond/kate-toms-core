if (!window.trustpilotScriptLoaded) {
	const script = document.createElement("script");
	script.src =
		"//widget.trustpilot.com/bootstrap/v5/tp.widget.bootstrap.min.js";
	script.async = true;
	document.body.appendChild(script);
	window.trustpilotScriptLoaded = true;
}
