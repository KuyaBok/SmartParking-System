document.addEventListener('DOMContentLoaded', function(){
    const maxSize = 16 * 1024 * 1024; // 16MB

    function clearPreview(container) {
        while (container.firstChild) container.removeChild(container.firstChild);
    }

    function getMaxFilesForInput(input) {
        if (!input) return 0;
        if (input.id === 'vehicle_images') return 4;
        if (input.id === 'license_images') return 2;
        return 6;
    }

    function handleFileInput(input) {
        const previewContainer = document.querySelector('#' + input.id + '-preview');
        const inputMaxFiles = getMaxFilesForInput(input);
        // listen for accumulated selections (fired by file_accumulator.js)
        input.addEventListener('accumulatedchange', function(e){
            clearPreview(previewContainer);
            const files = Array.from(e.detail.files || []);
            if (files.length === 0) return;
            if (files.length > inputMaxFiles) {
                alert('You can upload up to ' + inputMaxFiles + ' images for this field.');
                try { input.value = ''; } catch(err) {}
                return;
            }
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                if (file.size > maxSize) {
                    alert('One of the images is too large. Maximum size is ' + (maxSize/1024/1024) + 'MB.');
                    try { input.value = ''; } catch(err) {}
                    clearPreview(previewContainer);
                    return;
                }
                if (!file.type.startsWith('image/')) {
                    alert('Please select only image files.');
                    try { input.value = ''; } catch(err) {}
                    clearPreview(previewContainer);
                    return;
                }
                const wrapper = document.createElement('div');
                wrapper.className = 'preview-item';
                wrapper.style.position = 'relative';
                wrapper.style.display = 'inline-block';
                wrapper.style.marginRight = '8px';

                const img = document.createElement('img');
                img.style.maxWidth = '160px';
                img.style.display = 'block';
                img.style.borderRadius = '4px';
                img.src = URL.createObjectURL(file);

                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'preview-remove';
                btn.textContent = '×';
                btn.dataset.inputId = input.id;
                btn.dataset.index = i;
                btn.style.position = 'absolute';
                btn.style.top = '6px';
                btn.style.right = '6px';
                btn.style.background = '#fff';
                btn.style.border = '1px solid #eee';
                btn.style.borderRadius = '6px';
                btn.style.width = '26px';
                btn.style.height = '26px';
                btn.style.lineHeight = '22px';
                btn.style.cursor = 'pointer';

                wrapper.appendChild(img);
                wrapper.appendChild(btn);
                previewContainer.appendChild(wrapper);
            }
        });
    }

    document.querySelectorAll('.vehicle-image-input, .license-image-input').forEach(input => handleFileInput(input));
});
