function updateState() {
	var elements = document.getElementsByName("active[]");
	var target = document.getElementById("act").checked;

	for (var i = 0; i < elements.length; i++) {
		elements[i].checked = target;
	}
}