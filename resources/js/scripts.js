document.addEventListener('DOMContentLoaded', function() {
    const colorPicker = document.getElementById('color_picker');
    const colorInput = document.getElementById('color_code');

    // Update text input when color is picked
    colorPicker.addEventListener('input', function() {
        colorInput.value = colorPicker.value; // Update the text input value
    });

    // Update color picker when text input is changed
    colorInput.addEventListener('input', function() {
        colorPicker.value = colorInput.value; // Update the color picker value
    });
});
