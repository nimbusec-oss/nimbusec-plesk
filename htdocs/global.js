function updateState(id) {
	var elements = document.getElementsByName("active" + id + "[]");
	var target = document.getElementById("act" + id).checked;

	for (var i = 0; i < elements.length; i++) {
		elements[i].checked = target;
	}
}