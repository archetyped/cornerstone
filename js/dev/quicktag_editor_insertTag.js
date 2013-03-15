function edInsertTag(editorField, activeButton) {
	if (document.selection) {
		editorField.focus();
		var e = document.selection.createRange();
		if (e.text.length > 0) {
			e.text = edButtons[activeButton].tagStart + e.text + edButtons[activeButton].tagEnd;
		}
		else {
			if (!edCheckOpenTags(activeButton) || edButtons[activeButton].tagEnd == "") {
				e.text = edButtons[activeButton].tagStart;
				edAddTag(activeButton);
			}
			else {
				e.text = edButtons[activeButton].tagEnd;
				edRemoveTag(activeButton);
			}
		}
		editorField.focus();
	}
	else {
		if (editorField.selectionStart || editorField.selectionStart == "0") {
			var b = editorField.selectionStart, a = editorField.selectionEnd, g = a, f = editorField.scrollTop;
			if (b != a) {
				editorField.value = editorField.value.substring(0, b) + edButtons[activeButton].tagStart + editorField.value.substring(b, a) + edButtons[activeButton].tagEnd + editorField.value.substring(a, editorField.value.length);
				g += edButtons[activeButton].tagStart.length + edButtons[activeButton].tagEnd.length;
			}
			else {
				if (!edCheckOpenTags(activeButton) || edButtons[activeButton].tagEnd == "") {
					editorField.value = editorField.value.substring(0, b) + edButtons[activeButton].tagStart + editorField.value.substring(a, editorField.value.length);
					edAddTag(activeButton);
					g = b + edButtons[activeButton].tagStart.length;
				}
				else {
					editorField.value = editorField.value.substring(0, b) + edButtons[activeButton].tagEnd + editorField.value.substring(a, editorField.value.length);
					edRemoveTag(activeButton);
					g = b + edButtons[activeButton].tagEnd.length;
				}
			}
			
			editorField.focus();
			editorField.selectionStart = g;
			editorField.selectionEnd = g;
			editorField.scrollTop = f;
		}
		else {
			if (!edCheckOpenTags(activeButton) || edButtons[activeButton].tagEnd == "") {
				editorField.value += edButtons[activeButton].tagStart;
				edAddTag(activeButton);
			}
			else {
				editorField.value += edButtons[activeButton].tagEnd;
				edRemoveTag(activeButton);
			}
			editorField.focus();
		}
	}
}
