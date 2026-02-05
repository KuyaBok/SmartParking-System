// Force reload when navigating back/forward or when page is loaded from BFCache
(function(){
    function shouldReloadOnShow(event){
        // event.persisted is true when loaded from bfcache
        if (event.persisted) {
            window.location.reload();
            return;
        }
        // For browsers supporting Navigation Timing Level 2
        try {
            var entries = performance.getEntriesByType('navigation');
            if (entries && entries.length) {
                var nav = entries[0];
                if (nav.type === 'back_forward') {
                    window.location.reload();
                }
            } else if (performance.navigation) { // fallback
                if (performance.navigation.type === 2) {
                    window.location.reload();
                }
            }
        } catch (e) {
            // ignore
        }
    }

    window.addEventListener('pageshow', shouldReloadOnShow);

    // Also disable aggressive caching with meta headers (just in case)
    var meta = document.createElement('meta');
    meta.httpEquiv = 'Cache-Control';
    meta.content = 'no-store, no-cache, must-revalidate, max-age=0';
    document.getElementsByTagName('head')[0].appendChild(meta);
})();