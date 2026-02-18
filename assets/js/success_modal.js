document.addEventListener('DOMContentLoaded', function(){
    const ok = document.getElementById('modal-ok-btn');
    const overlay = document.getElementById('modal-overlay');
    if (!ok || !overlay) return;
    ok.addEventListener('click', function(){
        overlay.style.display = 'none';
    });
    // close when clicking outside the card
    overlay.addEventListener('click', function(e){ if (e.target === overlay) overlay.style.display = 'none'; });
});
