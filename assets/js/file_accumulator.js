document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.vehicle-image-input, .license-image-input').forEach(function(input){
        // store files in a DataTransfer so we can append across selections
        const dt = new DataTransfer();
        // expose the DataTransfer on the input for later removal
        input._dt = dt;
        input.addEventListener('change', function(e){
            const newFiles = Array.from(e.target.files || []);
            newFiles.forEach(f => dt.items.add(f));
            // assign combined files back to input
            input.files = dt.files;
            input._dt = dt;
            // dispatch custom event that previews can listen to
            const ev = new CustomEvent('accumulatedchange', { detail: { files: dt.files } });
            input.dispatchEvent(ev);
        });
    });

    // Handle remove-clicks from preview remove buttons
    document.addEventListener('click', function(e){
        const target = e.target;
        if (!target || !target.classList || !target.classList.contains('preview-remove')) return;
        const inputId = target.dataset.inputId;
        const idx = parseInt(target.dataset.index, 10);
        if (!inputId) return;
        const input = document.getElementById(inputId);
        if (!input || !input._dt) return;
        if (isNaN(idx)) return;
        try {
            input._dt.items.remove(idx);
            input.files = input._dt.files;
            const ev = new CustomEvent('accumulatedchange', { detail: { files: input._dt.files } });
            input.dispatchEvent(ev);
        } catch (err) {
            console.error('Failed to remove file from selection', err);
        }
    }, false);
});
