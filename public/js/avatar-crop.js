(function () {
    'use strict';

    var fileInput = document.querySelector('input[type="file"][data-avatar-crop]');
    var dataInput = document.querySelector('input[data-avatar-crop-data]');
    if (!fileInput || !dataInput || typeof Cropper === 'undefined') {
        return;
    }

    // Houder voor het te-croppen beeld, direct na het bestandsveld.
    var holder = document.createElement('div');
    holder.className = 'avatar-crop-holder mt-2';
    holder.style.maxWidth = '320px';
    holder.style.display = 'none';
    var img = document.createElement('img');
    img.alt = '';
    img.style.maxWidth = '100%';
    img.style.display = 'block';
    holder.appendChild(img);
    fileInput.parentNode.insertBefore(holder, fileInput.nextSibling);

    var cropper = null;

    fileInput.addEventListener('change', function () {
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
        dataInput.value = '';

        var file = fileInput.files && fileInput.files[0];
        if (!file) {
            holder.style.display = 'none';
            return;
        }

        img.src = URL.createObjectURL(file);
        holder.style.display = 'block';
        cropper = new Cropper(img, {
            aspectRatio: 1,
            viewMode: 1,
            autoCropArea: 1,
            background: false,
            // Coördinaten in bronpixels (afgerond) -> "x,y,size" voor de server.
            crop: function () {
                var d = cropper.getData(true);
                dataInput.value = d.x + ',' + d.y + ',' + d.width;
            }
        });
    });
})();
