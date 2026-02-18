document.addEventListener('DOMContentLoaded', function(){
    // simple lightbox (single image open)
    const modal = document.createElement('div');
    modal.className = 'lightbox-overlay';
    modal.innerHTML = '<div class="lightbox-inner"><img class="lightbox-img" src="" alt=""/><button class="lightbox-close">×</button></div>';
    document.body.appendChild(modal);
    const imgEl = modal.querySelector('.lightbox-img');
    const closeBtn = modal.querySelector('.lightbox-close');
    function open(src) { imgEl.src = src; modal.classList.add('open'); document.body.style.overflow = 'hidden'; }
    function close() { modal.classList.remove('open'); imgEl.src = ''; document.body.style.overflow = ''; }
    modal.addEventListener('click', function(e){ if (e.target === modal || e.target === closeBtn) close(); });
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape') close(); });
    document.querySelectorAll('.vehicle-thumb, .thumb-wrapper > img').forEach(function(el){
        el.addEventListener('click', function(e){ e.preventDefault(); const src = el.tagName.toLowerCase() === 'img' ? el.src : (el.href || el.getAttribute('href')); open(src); });
    });
});
