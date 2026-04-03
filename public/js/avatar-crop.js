(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('form-conta');
        var input = document.getElementById('avatar-input');
        var img = document.getElementById('avatar-crop-img');
        var wrap = document.getElementById('avatar-crop-wrap');
        if (!form || !input || !img || !wrap) {
            return;
        }
        if (typeof Cropper === 'undefined') {
            return;
        }

        var cropper = null;
        var objectUrl = null;

        function revokeUrl() {
            if (objectUrl) {
                URL.revokeObjectURL(objectUrl);
                objectUrl = null;
            }
        }

        input.addEventListener('change', function () {
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
            revokeUrl();
            var f = input.files && input.files[0];
            if (!f) {
                wrap.hidden = true;
                img.removeAttribute('src');
                return;
            }
            objectUrl = URL.createObjectURL(f);
            wrap.hidden = false;
            img.onload = function () {
                img.onload = null;
                if (cropper) {
                    cropper.destroy();
                }
                cropper = new Cropper(img, {
                    aspectRatio: 1,
                    viewMode: 1,
                    dragMode: 'move',
                    autoCropArea: 0.92,
                });
            };
            img.src = objectUrl;
        });

        form.addEventListener('submit', function onSubmit(e) {
            if (!cropper) {
                return;
            }
            e.preventDefault();
            cropper.getCroppedCanvas({ width: 512, height: 512 }).toBlob(
                function (blob) {
                    if (!blob) {
                        return;
                    }
                    var dt = new DataTransfer();
                    dt.items.add(new File([blob], 'avatar.jpg', { type: 'image/jpeg' }));
                    input.files = dt.files;
                    cropper.destroy();
                    cropper = null;
                    wrap.hidden = true;
                    revokeUrl();
                    form.removeEventListener('submit', onSubmit);
                    form.submit();
                },
                'image/jpeg',
                0.92
            );
        });
    });
})();
