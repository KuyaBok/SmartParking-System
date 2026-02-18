// Owner name suggestions: fetches suggestions and populates datalist
(function(){
    const input = document.getElementById('owner-search');
    const datalist = document.getElementById('owner-suggestions');
    const typeSelect = document.querySelector('select[name="type"]');
    let timer = null;

    function clearOptions() {
        while (datalist && datalist.firstChild) datalist.removeChild(datalist.firstChild);
    }

    function fetchSuggestions(term) {
        const type = typeSelect ? typeSelect.value : 'all';
        const url = 'ajax_owner_suggest.php?term=' + encodeURIComponent(term) + '&type=' + encodeURIComponent(type);
        fetch(url)
            .then(r => r.json())
            .then(data => {
                clearOptions();
                if (Array.isArray(data) && datalist) {
                    data.forEach(name => {
                        const opt = document.createElement('option');
                        opt.value = name;
                        datalist.appendChild(opt);
                    });
                }
            })
            .catch(() => { clearOptions(); });
    }

    if (!input) return;

    input.addEventListener('input', function(){
        const v = this.value.trim();
        if (timer) clearTimeout(timer);
        if (v.length === 0) { clearOptions(); return; }
        // query after slight debounce
        timer = setTimeout(() => fetchSuggestions(v), 200);
    });

    // Update suggestions when type filter changes
    if (typeSelect) {
        typeSelect.addEventListener('change', function(){
            const v = input.value.trim();
            if (v.length > 0) fetchSuggestions(v);
        });
    }
})();
